<?php

namespace app\controllers;

use app\common\Common;
use app\models\Cashier;
use app\models\CashierSearch;
use yii\filters\VerbFilter;

class TeamController extends \yii\web\Controller
{
    public function init()
    {
        header("Access-Control-Allow-Origin: *");
        parent::init();
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
            'selfFilter' => [
                'class' => 'app\components\AccessFilter',
                'only' => ['*'],
            ],
        ];
    }


    public function beforeAction($action){

        $queryParams = \Yii::$app->request->bodyParams;
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


        \Yii::info(json_encode($logData, 256), 'team_init_log');


        return parent::beforeAction($action);

    }


    /**
     * @return array
     * 获取团队信息（团队总人数，当日收款情况）
     */
    public function actionTeamInfo()
    {
        \Yii::info(json_encode($_POST, 256), 'Team/TeamInfo1');
        $username = \Yii::$app->request->post('username');
        //当前团队最高级
        $first = Cashier::find()->where(['username' => $username])->andWhere(['<', 'cashier_status', 2])->select(['wechat_rate', 'parent_name', 'username', 'alipay_rate', 'agent_class'])->one();
        //团队下所有成员
        $all = Cashier::calcTeam($first);
        //包括他自己
        array_push($all, $first);
        //统计信息
        $data = [];
        //团队总人数
        $data['totalCashier'] = count($all) - 1;

        $data['info'] = Cashier::teamIncome($username);
        $ret = Common::ret();
        $ret['data'] = $data;
        $ret['result'] = 1;
        return $ret;
    }

    /**
     * @return array
     * 直接下级列表
     */
    public function actionChildList()
    {
        \Yii::info(json_encode($_POST, 256), 'Team/ChildList1');
        $returnData = Common::ret();

        $searchModel = new CashierSearch();

        $username = \Yii::$app->request->post('username');
        $data = $searchModel->search(\Yii::$app->request->bodyParams, $username);

        $returnData['result'] = is_array($data) ? 1 : 0;
        $returnData['msg'] = is_array($data) ? (isset($data['total']) && is_numeric($data['total']) && $data['total'] > 0 ? \Yii::t('app', 'succeed') : \Yii::t('app', 'no_data')) : $data;
        $returnData['data'] = is_array($data) ? $data : array();

        return $returnData;
    }

    /**
     * @return array
     * 添加下级
     */
    public function actionAddMember()
    {
        \Yii::info(json_encode($_POST, 256), 'Team/AddMember1');
        $returnData = Common::ret();
        //当前用户名
        $username = \Yii::$app->request->post('username');

        $lockKey = $username . 'AddMember';
        $isContinue = Common::redisLock($lockKey, 3);
        if ($isContinue === false) {
            $returnData['result'] = 0;
            $returnData['msg'] = '操作频繁，3秒后再试';
            return $returnData;
        }

        $first = Cashier::find()->where(['username' => $username, 'cashier_status' => 1])->one();
        if (!$first) {
            $returnData['msg'] = '当前用户状态不正常';
            return $returnData;
        }

        $data['username'] = \Yii::$app->request->post('child_name');
        $data['login_password'] = \Yii::$app->request->post('login_password');
        $data['wechat_rate'] = \Yii::$app->request->post('wechat_rate',0);
        $data['alipay_rate'] = \Yii::$app->request->post('alipay_rate',0);
        $data['union_pay_rate'] = \Yii::$app->request->post('union_pay_rate',0);
        $data['bank_card_rate'] = \Yii::$app->request->post('bank_card_rate',0);
        $data['bank_card_amount'] =0;
        $data['union_pay_amount'] =0;

        if ($data['wechat_rate'] > $first->wechat_rate) {
            $returnData['msg'] = '下级微信费率不能大于上级微信费率';
            return $returnData;
        }

        if ($data['alipay_rate'] > $first->alipay_rate) {
            $returnData['msg'] = '下级支付宝费率不能大于上级支付宝费率';
            return $returnData;
        }

        if ($data['union_pay_rate'] > $first->union_pay_rate) {
            $returnData['msg'] = '下级云闪付费率不能大于上级云闪付费率';
            return $returnData;
        }

        if ($data['bank_card_rate'] > $first->bank_card_rate) {
            $returnData['msg'] = '下级银行卡费率不能大于上级银行卡费率';
            return $returnData;
        }

        $data['parent_name'] = $username;
        $data['cashier_status'] = 1;
        $data['wechat'] = \Yii::$app->request->post('wechat', '');
        $data['alipay'] = \Yii::$app->request->post('alipay', '');
        $data['telephone'] = \Yii::$app->request->post('telephone', '');
        $data['remark'] = \Yii::$app->request->post('remark', '');
        $res = Cashier::addMember($data);
        if ($res === true) {

            $ga = new \PHPGangsta_GoogleAuthenticator();
            $googleKey = $ga->createSecret();
            \Yii::$app->redis->set($data['username'] . 'GoogleC', $googleKey);

            $returnData['msg'] = '添加下级成功';
            $returnData['result'] = 1;
        } else {
            $returnData['msg'] = $res;
        }
        return $returnData;
    }

    /**
     * @return array
     * 修改下级
     */
    public function actionEditMember()
    {
        \Yii::info(json_encode($_POST, 256), 'Team/editMember1');
        $returnData = Common::ret();
        //当前用户名
        $username = \Yii::$app->request->post('username');

        $lockKey = $username . 'EditMember';
        $isContinue = Common::redisLock($lockKey, 3);
        if ($isContinue === false) {
            $returnData['result'] = 0;
            $returnData['msg'] = '操作频繁，3秒后再试';
            return $returnData;
        }

        $id = \Yii::$app->request->post('id');
        $data['login_password'] = \Yii::$app->request->post('login_password', '');
        $data['wechat_rate'] = \Yii::$app->request->post('wechat_rate', '');
        $data['alipay_rate'] = \Yii::$app->request->post('alipay_rate', '');
        $data['union_pay_rate'] = \Yii::$app->request->post('union_pay_rate', '');
        $data['bank_card_rate'] = \Yii::$app->request->post('bank_card_rate', '');
        $data['parent_name'] = $username;
        $data['cashier_status'] = \Yii::$app->request->post('cashier_status', '');
        $data['wechat'] = \Yii::$app->request->post('wechat', '');
        $data['alipay'] = \Yii::$app->request->post('alipay', '');
        $data['telephone'] = \Yii::$app->request->post('telephone', '');
        $data['remark'] = \Yii::$app->request->post('remark', '');

        $res = Cashier::editMember($data, $id, $username);

        if ($res === true) {
            $returnData['msg'] = '修改下级成功';
            $returnData['result'] = 1;
        } else {
            $returnData['msg'] = $res;
        }
        return $returnData;
    }

}
