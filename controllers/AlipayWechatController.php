<?php

namespace app\controllers;

use app\common\Common;
use app\jobs\AlipayWechatJob;
use app\models\Cashier;
use app\models\QrCode;
use app\models\TransactionFlow;
use yii\filters\VerbFilter;
use yii\web\Controller;

class AlipayWechatController extends Controller
{
    public $salt = 'Fc4zk5UpukHmaL0y';

    public function init()
    {
        \Yii::$app->response->format = 'json';
    }

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    '*' => ['post'],
                ],
            ],
        ];
    }


    public function beforeAction($action){

        $queryParams = \Yii::$app->request->rawBody;
        $redisUser = array();
        if(isset($queryParams['token']) && $queryParams['token']){
            if(\Yii::$app->redis->get('User_'.$queryParams['token'])){
                $redisUser = json_decode(\Yii::$app->redis->get('User_'.$queryParams['token']), true);
            }
        }


        //日志数据
        $logData = array(
            'uri' => \Yii::$app->controller->id .'_'.\Yii::$app->controller->action->id,  //请求的uri
            'query_params'=>$queryParams,  //请求参数
            'client_ip' => Common::getClientIp(),  //用户ip
            'redis_user'=>$redisUser,  //用户信息，  登录后才有
        );


        \Yii::info(json_encode($logData, 256), 'alipaywechat_init_log');


        return parent::beforeAction($action);

    }


    /**
     * 支付宝、微信客户端登录
     */
    public function actionLogin()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'AlipaWechat_Login_Params');

        if (!$datas) {
            return ['msg' => '数据为空', 'result' => 0];
        }
        $datas = json_decode($datas, 1);
        if (!is_array($datas)) {
            return ['msg' => '未收到有效数据', 'result' => 0];
        }

        if (!isset($datas['username']) || empty($datas['username']) || !isset($datas['password']) || empty($datas['password']) || !isset($datas['alipay_account']) || empty($datas['alipay_account']) || !isset($datas['wechat_account']) || empty($datas['wechat_account'])) {
            return ['msg' => '用户名/密码/账号必须填写', 'result' => 0];
        }

        $user = Cashier::find()->where(['username' => $datas['username']])->one();
        if (!$user) {
            return ['msg' => '用户不存在', 'result' => 0];
        }

        $validate = md5($user->login_password . $this->salt);
        \Yii::info($validate . '-' . $datas['password'], 'AlipaWechat_Login_Validate');
        if (trim($validate) != trim($datas['password'])) {
            return ['msg' => '用户名或密码错误', 'result' => 0];
        }

        $qrcode = QrCode::find()->where(['username' => $datas['username']])->andWhere(['in', 'qr_status', [1, 2]])->orWhere(['qr_account' => $datas['alipay_account']])->orWhere(['qr_account' => $datas['wechat_account']])->one();
        if (!$qrcode) {
            return ['msg' => '通过账号未找到此二维码信息', 'result' => 0];
        }

        $token = md5($user->username . $user->login_password . time());
        \Yii::$app->redis->setex($token, 86400, $token);
        return ['data' => $token, 'msg' => '登录成功', 'result' => 1];
    }

    /**
     * @return array
     * 银商码退出
     */
    public function actionLogout()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'AlipayWechat_Logout_Params');

        if (!$datas) {
            return ['msg' => '数据为空', 'result' => 0];
        }
        $datas = json_decode($datas, 1);
        if (!is_array($datas)) {
            return ['msg' => '未收到有效数据', 'result' => 0];
        }

        if (!isset($datas['username']) || empty($datas['username']) || !isset($datas['token']) || empty($datas['token'])) {
            return ['msg' => '用户名/TOKEN必须填写', 'result' => 0];
        }

        \Yii::$app->redis->del($datas['username'] . $datas['token']);

        return ['msg' => '退出成功', 'result' => 1];
    }

    /**
     * 接收流水
     */
    public function actionTransaction()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'AlipayWechat_Transaction_Params');
        if (!$datas) {
            \Yii::info(json_encode(['data' => $datas, 'msg' => '数据为空']), 'AlipayWechat_Transaction_Null');
            return ['msg' => '数据为空', 'result' => 0];
        }
        $datas = json_decode($datas, 1);
        if (!is_array($datas)) {
            \Yii::info(json_encode(['data' => $datas, 'msg' => '未收到有效数据']), 'AlipayWechat_Transaction_NoReceived');
            return ['msg' => '未收到有效数据', 'result' => 0];
        }

        if (!(\Yii::$app->redis->get($datas['token']))) {
            \Yii::info(json_encode(['data' => $datas, 'msg' => '非有效TOKEN']), 'AlipayWechat_Transaction_Token');
            return ['msg' => '非有效TOKEN', 'result' => 0];
        }

        if (md5(json_encode($datas['data'], 256) . $this->salt) != $datas['verify']) {
            \Yii::info(json_encode(['data' => $datas, 'msg' => '非法数据']), 'AlipayWechat_Transaction_Illege');
            return ['msg' => '非法数据', 'result' => 0];
        }

        try {
            $success = [];
            $failed = [];
            foreach ($datas['data'] as $k => $v) {
                if (\Yii::$app->redis->get($v['payTime'] . $v['money'] . $v['type'] . $v['account'])) {
                    \Yii::info(json_encode($v, 256), 'AlipayWechat_Transaction_AlreadyHas');
                    continue;
                }
                \Yii::$app->redis->setex($v['payTime'] . $v['money'] . $v['type'] . $v['account'], 90000, 1);
                $v['tradeNo'] = trim($datas['verify']);
                $transactionFlow = new TransactionFlow();
                if ($v['type'] == 1) {
                    $clientId = '111';
                    $client_code = '支付宝个码';
                    $trade_type = 1;
                } elseif ($v['type'] == 2) {
                    $clientId = '222';
                    $client_code = '微信个码';
                    $trade_type = 2;
                }
                $transactionFlow->client_id = $clientId;
                $transactionFlow->client_code = $client_code;
                $transactionFlow->trade_type = $trade_type;
                $transactionFlow->trade_cate = $trade_type;
                $transactionFlow->trans_id = $v['tradeNo'];
                $transactionFlow->trans_account = $v['account'];
                $transactionFlow->trans_time = $v['payTime'];
                $transactionFlow->trans_type = 1;
                $transactionFlow->trans_amount = $v['money'];
                $transactionFlow->trans_status = 0;
                $transactionFlow->md5_sign = md5($datas['verify']);
                $transactionFlow->pick_at = $transactionFlow->insert_at = $transactionFlow->update_at = date('Y-m-d H:i:s');
                if (!($transactionFlow->validate())) {
                    \Yii::$app->redis->del($v['payTime'] . $v['money'] . $v['type'] . $v['account']);
                    array_push($failed, $transactionFlow);
                    \Yii::info(json_encode(['data' => $transactionFlow, 'msg' => Common::getModelError($transactionFlow)], 256), 'AlipayWechat_Transaction_Validate_No');
                    continue;
                }
                if ($transactionFlow->save()) {
                    //队列去匹配订单
                    $queueRes = \Yii::$app->qmqueue->push(new AlipayWechatJob([
                        'trans_id' => $transactionFlow->trans_id,
                    ]));
                    \Yii::info(json_encode(['data' => $transactionFlow, 'res' => $queueRes], 256), 'AlipayWechat_Transaction_Save_Ok');
                    array_push($success, $transactionFlow);
                    continue;
                } else {
                    \Yii::$app->redis->del($v['payType'] . $v['tradeNo']);
                    array_push($failed, $transactionFlow);
                    \Yii::info(json_encode($transactionFlow, 256), 'AlipayWechat_Transaction_Save_No');
                    continue;
                }
            }
        } catch (\Exception $e) {
            \Yii::info($e->getMessage() . '-' . $e->getFile() . '-' . $e->getLine(), 'AlipayWechat_Transaction_Error');
        }

        //每次有流水上传，在线时间变为1天
        \Yii::$app->redis->setex($datas['token'], 86400, $datas['token']);
        \Yii::info(json_encode(['success' => $success, 'failed' => $failed], 256), 'AlipayWechat_Transaction_Result');
        return ['msg' => '流水上报成功', 'result' => 1];
    }
}