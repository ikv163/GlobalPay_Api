<?php

namespace app\controllers;

use app\common\Common;
use app\jobs\YinshangJob;
use app\models\Admin;
use app\models\TransactionFlow;
use yii\filters\VerbFilter;
use yii\web\Controller;

class TransactionFlowController extends Controller
{
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


    public function beforeAction($action)
    {

        $queryParams = \Yii::$app->request->rawBody;
        $redisUser = array();
        if (isset($queryParams['token']) && $queryParams['token']) {
            if (\Yii::$app->redis->get('User_' . $queryParams['token'])) {
                $redisUser = json_decode(\Yii::$app->redis->get('User_' . $queryParams['token']), true);
            }
        }


        //日志数据
        $logData = array(
            'uri' => \Yii::$app->controller->id . '_' . \Yii::$app->controller->action->id,  //请求的uri
            'query_params' => $queryParams,  //请求参数
            'client_ip' => Common::getClientIp(),  //用户ip
            'redis_user' => $redisUser,  //用户信息，  登录后才有
        );


        \Yii::info(json_encode($logData, 256), 'transactionflow_init_log');


        return parent::beforeAction($action);

    }


    /**
     * 银商码登录
     */
    public function actionYsLogin()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'TrasactionFlow_YsLogin_params');

        if (!$datas) {
            return ['msg' => '数据为空', 'result' => 0];
        }
        $datas = json_decode($datas, 1);
        if (!is_array($datas)) {
            return ['msg' => '未收到有效数据', 'result' => 0];
        }

        if (!isset($datas['username']) || empty($datas['username']) || !isset($datas['password']) || empty($datas['password'])) {
            return ['msg' => '用户名/密码必须填写', 'result' => 0];
        }

        $user = Admin::find()->where(['username' => $datas['username']])->one();
        if (!$user) {
            return ['msg' => '用户不存在', 'result' => 0];
        }
        if (!(\Yii::$app->security->validatePassword($datas['password'], $user->password))) {
            return ['msg' => '用户密码错误', 'result' => 0];
        }
        $token = md5($user->username . $user->password . time());
        \Yii::$app->redis->setex(($user->username) . $token, 86400, $token);
        return ['data' => $token, 'msg' => '登录成功', 'result' => 1];
    }

    /**
     * @return array
     * 银商码退出
     */
    public function actionYsLogout()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'TrasactionFlow_YsLogout_params');

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
     * 接收银商码的流水
     */
    public function actionYinshang()
    {
        $datas = \Yii::$app->request->rawBody;
        \Yii::info(json_encode($datas, 256), 'TrasactionFlow_Yinshang_Params');

        if (!$datas) {
            return ['msg' => '数据为空', 'result' => 0];
        }
        $datas = json_decode($datas, 1);
        if (!is_array($datas)) {
            return ['msg' => '未收到有效数据', 'result' => 0];
        }

        //固定khd888账号获取银商码流水
        $username = 'khd888';
        if (!(\Yii::$app->redis->get($username . $datas['token']))) {
            return ['msg' => '非有效TOKEN', 'result' => 0];
        }

        try {
            $success = [];
            $failed = [];
            foreach ($datas['data'] as $k => $v) {
                if (\Yii::$app->redis->get($v['tradeId'])) {
                    \Yii::info(json_encode($v, 256), 'TransactionFlow_AlreadyHas');
                    continue;
                }
                \Yii::$app->redis->setex($v['tradeId'], 90000, 1);
                $v['tradeId'] = trim($v['tradeId']);
                $transactionFlow = new TransactionFlow();
                $transactionFlow->client_id = 888;
                $transactionFlow->client_code = '银商码';
                $transactionFlow->trade_type = 3;
                if ($v['payType'] == '微信') {
                    $transactionFlow->trade_cate = 2;
                } elseif ($v['payType'] == '支付宝') {
                    $transactionFlow->trade_cate = 1;
                }
                $transactionFlow->trans_id = $v['tradeId'];
                $transactionFlow->trans_account = $v['merchantId'];
                $transactionFlow->trans_time = $v['datetime'];
                $transactionFlow->trans_type = 1;
                $transactionFlow->trans_amount = bcdiv($v['money'], 100, 2);
                $transactionFlow->trans_status = $v['payStatus'] == '成功' ? 0 : 3;
                $transactionFlow->md5_sign = md5($v['tradeId']);
                $transactionFlow->pick_at = $transactionFlow->insert_at = $transactionFlow->update_at = date('Y-m-d H:i:s');
                if (!($transactionFlow->validate())) {
                    \Yii::$app->redis->del($v['tradeId']);
                    array_push($failed, $transactionFlow);
                    \Yii::info(json_encode(['data' => $transactionFlow, 'msg' => Common::getModelError($transactionFlow)], 256), 'TransactionFlow_Validate_No');
                    continue;
                }
                if ($transactionFlow->save()) {
                    //队列去匹配订单
                    \Yii::$app->qmqueue->push(new YinshangJob([
                        'trans_id' => $transactionFlow->trans_id,
                    ]));
                    array_push($success, $transactionFlow);
                    \Yii::info(json_encode($transactionFlow, 256), 'TransactionFlow_Save_Ok');
                    continue;
                } else {
                    \Yii::$app->redis->del($v['tradeId']);
                    array_push($failed, $transactionFlow);
                    \Yii::info(json_encode($transactionFlow, 256), 'TransactionFlow_Save_No');
                    continue;
                }
            }
        } catch (\Exception $e) {
            \Yii::info($e->getMessage() . '-' . $e->getFile() . '-' . $e->getLine(), 'TransactionFlow_Yinshang_Error');
        }

        //每次有流水上传，在线时间变为1天
        \Yii::$app->redis->setex($username . $datas['token'], 86400, $datas['token']);
        \Yii::info(json_encode(['success' => $success, 'failed' => $failed], 256), 'TrasactionFlow_Yinshang_Result');
        return ['msg' => '流水上报成功', 'result' => 1];
    }
}