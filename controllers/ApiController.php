<?php

namespace app\controllers;

use app\models\Deposit;
use app\models\QrCode;
use app\models\Withdraw;
use Pheanstalk\Exception;
use yii\filters\VerbFilter;
use yii\web\Controller;
use app\models\Cashier;
use app\models\SystemConfig;
use app\common\Common;
use GatewayWorker\Lib\Gateway;
use app\models\FinanceDetail;
use app\models\Order;

class ApiController extends Controller
{

    //支付宝好友红包回调签名密钥
    private $alipayRedEnvelopKey = 'Fc4zk%5UpukHmaLqt#*aqv';


    public function init()
    {
        header("Access-Control-Allow-Origin: *");
        \Yii::$app->response->format = 'json';
        parent::init();
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


        \Yii::info(json_encode($logData, 256), 'api_init_log');


        return parent::beforeAction($action);

    }


    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    '*' => ['post'],
                    'test111' => ['get'],
                    'counter' => ['get']
                ],
            ],
        ];
    }

    /**
     * 注册
     * @params  string      $username
     * @params  string      $password
     * @params  string      $inviteCode
     * @param string $captchaKey
     * @param string $captcha
     * @params  string      $userIp
     * @return  array
     */
    public function actionRegister()
    {
        \Yii::info(json_encode($_POST, 256), 'Api_Register_Params');
        $username = \Yii::$app->request->post('username');
        $password = \Yii::$app->request->post('password');
        $captchaKey = \Yii::$app->request->post('captchaKey');
        $captcha = \Yii::$app->request->post('captcha');
        $telephone = \Yii::$app->request->post('telephone');
        $inviteCode = \Yii::$app->request->post('inviteCode');
        $ip = \Yii::$app->request->post('userIp');

        $returnData = Common::ret();

        //检验数据

        //根据配置， 是否校验邀请码 (后台IGNORE_REGISTER_INVITE_CODE 配置值1表示不需要邀请码， 未配置或配置其他值表示需要校验)
        $ignoreInviteCode = SystemConfig::getSystemConfig('IGNORE_REGISTER_INVITE_CODE');
        if(!(is_numeric($ignoreInviteCode) && $ignoreInviteCode == 1)){
            if (!$inviteCode) {
                $returnData['msg'] = \Yii::t('app/error', 'invite_code_required');
                return $returnData;
            }
        }

        if (!$username) {
            $returnData['msg'] = \Yii::t('app/error', 'username_required');
            return $returnData;
        }
        if (!$password) {
            $returnData['msg'] = \Yii::t('app/error', 'password_required');
            return $returnData;
        }
        if (!$captchaKey) {
            $returnData['msg'] = \Yii::t('app/error', 'captcha_key_required');
            return $returnData;
        }
        if (!$captcha) {
            $returnData['msg'] = \Yii::t('app/error', 'captcha_required');
            return $returnData;
        }
        if (!$telephone) {
            $returnData['msg'] = '手机号码必填';
            return $returnData;
        }
        if (!$ip) {
            $returnData['msg'] = \Yii::t('app/error', 'ip_required');
            return $returnData;
        }
        //获取并比对验证码
        $redisCaptcha = \Yii::$app->redis->get('Captcha_' . $captchaKey);
        if (!$redisCaptcha) {
            $returnData['msg'] = \Yii::t('app/error', 'captcha_expired');
            return $returnData;
        }

        if (strtolower($redisCaptcha) != strtolower($captcha)) {
            $returnData['msg'] = \Yii::t('app/error', 'captcha_error');
            return $returnData;
        }

        //验证码ok,  删除redis中的该验证码
        \Yii::$app->redis->del('Captcha_' . $captchaKey);

        //验证用户名是否存在
        if (Cashier::find()->where("username = :username", array(':username' => $username))->asArray()->one()) {
            $returnData['msg'] = \Yii::t('app/error', 'username_exists');
            return $returnData;
        }

        //根据是否有pid来初始化支付宝、微信佣金费率
        $wechatRate = $alipayRate=$unionPayRate=$bankCardRate = 0;
        $agentName = '';
        $agentClass = 1;
        $isValidInviteCode = false;
        if ($inviteCode) {
            //有上级代理的，取代理信息，写入其上级代理信息，费率默认先都给0， 后续让代理去后台再设置
            $agentInfo = Cashier::find()->select('username, agent_class')->where("invite_code = :code", array(':code' => $inviteCode))->asArray()->one();
            if ($agentInfo && isset($agentInfo['username']) && $agentInfo['username']) {
                $agentName = $agentInfo['username'];
                $agentClass = isset($agentInfo['agent_class']) && is_numeric($agentInfo['agent_class']) && intval($agentInfo['agent_class']) == $agentInfo['agent_class'] ? $agentInfo['agent_class'] + 1 : 1;
                $isValidInviteCode = true;
            } else {
                $returnData['msg'] = '邀请码无效';
                return $returnData;
            }
        } else {
            //没有上级代理的，取后台配置的默认费率
            $wechatRateConfig = SystemConfig::getSystemConfig('WechatInitRate');
            if (is_numeric($wechatRateConfig) && $wechatRateConfig >= 0) {
                $wechatRate = $wechatRateConfig;
            }

            $alipayRateConfig = SystemConfig::getSystemConfig('AlipayInitRate');
            if (is_numeric($alipayRateConfig) && $alipayRateConfig >= 0) {
                $alipayRate = $alipayRateConfig;
            }

            $unionPayInitRateConfig = SystemConfig::getSystemConfig('UnionPayInitRate');
            if (is_numeric($unionPayInitRateConfig) && $unionPayInitRateConfig >= 0) {
                $unionPayRate = $unionPayInitRateConfig;
            }

            $bankCardInitRateConfig = SystemConfig::getSystemConfig('BankCardInitRate');
            if (is_numeric($bankCardInitRateConfig) && $bankCardInitRateConfig >= 0) {
                $bankCardRate = $bankCardInitRateConfig;
            }
        }

        $cashierInfo = array(
            'username' => $username,
            'login_password' => md5($password),
            'pay_password' => '',
            'wechat_rate' => $wechatRate,
            'alipay_rate' => $alipayRate,
            'union_pay_rate' => $unionPayRate,
            'bank_card_rate' => $bankCardRate,
            'union_pay_amount' => 0,
            'bank_card_amount' => 0,
            'telephone' => $telephone,
            'parent_name' => $agentName,
            'insert_at' => date('Y-m-d H:i:s'),
            'invite_code' => Cashier::generateCashierInviteCode(),
            'cashier_status' => 1,
            'agent_class' => $agentClass,
        );
        $model = new Cashier($cashierInfo);
        $res = $model->save();
        $returnData['result'] = intval($res);
        $returnData['msg'] = $res ? \Yii::t('app/error', 'register_succeed') : \Yii::t('app/error', 'register_failed');

        //注册成功，将用户置为登录态
        if ($res) {
            $key = md5($username . $password . time());
            $ga = new \PHPGangsta_GoogleAuthenticator();
            $googleKey = $ga->createSecret();
            \Yii::$app->redis->set($username . 'GoogleC', $googleKey);

            //保证单点登录，以用户名找到token, 再删除redis中token对应值
            $token = \Yii::$app->redis->get($username);
            if ($token && \Yii::$app->redis->get('User_' . $token)) {
                \Yii::$app->redis->del('User_' . $token);
            }

            $redisData = array(
                'username' => $username,
                'userIp' => $ip,
            );

            //以用户名为key保存用户身份token,  再以token为key保存用户身份信息
            \Yii::$app->redis->setex($username, \Yii::$app->params['Login_Status_Expire_Time'], $key);
            \Yii::$app->redis->setex('User_' . $key, \Yii::$app->params['Login_Status_Expire_Time'], json_encode($redisData));

            $returnData['data'] = array(
                'token' => $key,
                'username' => $username,
                'parent_name' => $agentName,
                'income' => '0.00',
                'security_money' => '0.00',
                'wechat_amount' => '0.00',
                'alipay_amount' => '0.00',
                'union_pay_amount' => '0.00',
                'bank_card_amount' => '0.00',
                'wechat_rate' => $cashierInfo['wechat_rate'],
                'alipay_rate' => $cashierInfo['alipay_rate'],
                'union_pay_rate' => $cashierInfo['union_pay_rate'],
                'bank_card_rate' => $cashierInfo['bank_card_rate'],
                'wechat' => '',
                'alipay' => '',
                'telephone' => $cashierInfo['telephone'],
                'agent_class' => $cashierInfo['agent_class'],
                'invite_code' => $cashierInfo['invite_code'],
                'cashier_status' => $cashierInfo['cashier_status'],
                'insert_at' => date('Y-m-d H:i:s'),
                'login_at' => date('Y-m-d H:i:s'),
                'promote_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/site/reg?p=' . $cashierInfo['invite_code'],
                'available_withdraw_amount' => 0.00,
            );
            $returnData['msg'] = $googleKey;

            unset($key);
            unset($redisData);

            //填写了邀请码， 但邀请码无效，还是让用户继续注册
            if ($inviteCode && !$isValidInviteCode) {
                $returnData['result'] = -1;
                $returnData['msg'] = \Yii::t('app/error', 'register_succeed');
                \Yii::info(json_encode(\Yii::$app->request->bodyParams, JSON_UNESCAPED_UNICODE), 'api/register-succeed-invalid-invitecode');

            }
        }

        return $returnData;
    }

    /**
     * 登录
     * @param string $username
     * @param string $password
     * @param string $captchaKey
     * @param string $captcha
     * @param string $userIp
     * @return array
     */
    public function actionLogin()
    {
        \Yii::info(json_encode($_POST, 256), 'Api_Login_Params');
        $username = \Yii::$app->request->post('username');
        $password = \Yii::$app->request->post('password');
//        $captchaKey = \Yii::$app->request->post('captchaKey');
//        $captcha = \Yii::$app->request->post('captcha');
        $google = \Yii::$app->request->post('google');
        $ip = \Yii::$app->request->post('userIp');

        $returnData = Common::ret();

        //检验数据
        $ignoreVerifyCode = SystemConfig::getSystemConfig('IGNORE_REGISTER_INVITE_CODE');
        if(!(is_numeric($ignoreVerifyCode) && $ignoreVerifyCode == 1)){
            if (!$google || strlen($google) != 6) {
                $returnData['msg'] = '谷歌验证码不正确';
                return $returnData;
            }
        }


        if (!$username) {
            $returnData['msg'] = \Yii::t('app/error', 'username_required');
            return $returnData;
        }

        if (!$password) {
            $returnData['msg'] = \Yii::t('app/error', 'password_required');
            return $returnData;
        }

//        if (!$captchaKey) {
//            $returnData['msg'] = \Yii::t('app/error', 'captcha_key_required');
//            return $returnData;
//        }
//
//        if (!$captcha) {
//            $returnData['msg'] = \Yii::t('app/error', 'captcha_required');
//            return $returnData;
//        }

        if (!$ip) {
            $returnData['msg'] = \Yii::t('app/error', 'ip_required');
            return $returnData;
        }


        if(!(is_numeric($ignoreVerifyCode) && $ignoreVerifyCode == 1)){
            $ga = new \PHPGangsta_GoogleAuthenticator();
            $googleKey = \Yii::$app->redis->get($username . 'GoogleC');
            if (!$ga->verifyCode($googleKey, $google, 2)) {
                $returnData['msg'] = '谷歌验证码错误';
                return $returnData;
            }
        }


        //获取并比对验证码
//        $redisCaptcha = \Yii::$app->redis->get('Captcha_' . $captchaKey);
//        if (!$redisCaptcha) {
//            $returnData['msg'] = \Yii::t('app/error', 'captcha_expired');
//            return $returnData;
//        }

//        if (strtolower($redisCaptcha) != strtolower($captcha)) {
//            $returnData['msg'] = \Yii::t('app/error', 'captcha_error');
//            return $returnData;
//        }

        //验证码ok,  删除redis中的该验证码
//        \Yii::$app->redis->del('Captcha_' . $captchaKey);

        //查询用户信息
        $userInfo = Cashier::find()->where(['username' => $username, 'login_password' => md5($password)])->asArray()->one();
        if (!$userInfo) {
            $returnData['msg'] = '账号不存在或密码错误';
            return $returnData;
        }

        if (isset($userInfo['cashier_status']) && is_numeric($userInfo['cashier_status']) && $userInfo['cashier_status'] != Cashier::$CashierStatusOn) {
            $returnData['msg'] = \Yii::t('app/error', 'user_status_unnormal');
            return $returnData;
        }

        //校验完毕， 保存相关数据到redis
        $key = md5($username . $password . time());

        //保证单点登录，以用户名找到token, 再删除redis中token对应值
        $token = \Yii::$app->redis->get($username);
        if ($token && \Yii::$app->redis->get('User_' . $token)) {
            \Yii::$app->redis->del('User_' . $token);
        }

        $redisData = array(
            'username' => $username,
            'userIp' => $ip,
        );

        //以用户名为key保存用户身份token,  再以token为key保存用户身份信息
        \Yii::$app->redis->setex($username, \Yii::$app->params['Login_Status_Expire_Time'], $key);
        \Yii::$app->redis->setex('User_' . $key, \Yii::$app->params['Login_Status_Expire_Time'], json_encode($redisData));

        $returnData['result'] = 1;
        $returnData['msg'] = \Yii::t('app', 'succeed');

        //计算用户可提现金额
        $withdrawHandlingFee = Withdraw::calHandlingFee($userInfo['security_money']);
        $availableWithdrawAmount = $userInfo['security_money'] >= $withdrawHandlingFee ? round($userInfo['security_money'] - $withdrawHandlingFee, 2) : 0.00;

        $returnData['data'] = array(
            'token' => $key,
            'username' => $userInfo['username'],
            'parent_name' => $userInfo['parent_name'],
            'income' => $userInfo['income'],
            'security_money' => $userInfo['security_money'],
            'wechat_amount' => $userInfo['wechat_amount'],
            'alipay_amount' => $userInfo['alipay_amount'],
            'wechat_rate' => $userInfo['wechat_rate'],
            'alipay_rate' => $userInfo['alipay_rate'],
            'union_pay_rate' => $userInfo['union_pay_rate'],
            'bank_card_rate' => $userInfo['bank_card_rate'],
            'union_pay_amount' => $userInfo['union_pay_amount'],
            'bank_card_amount' => $userInfo['bank_card_amount'],
            'wechat' => $userInfo['wechat'],
            'alipay' => $userInfo['alipay'],
            'telephone' => $userInfo['telephone'],
            'agent_class' => $userInfo['agent_class'],
            'invite_code' => $userInfo['invite_code'],
            'cashier_status' => $userInfo['cashier_status'],
            'insert_at' => $userInfo['insert_at'],
            'login_at' => $userInfo['login_at'],
            'promote_url' => isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http' . '://' . $_SERVER['SERVER_NAME'] . '/site/reg?p=' . $userInfo['invite_code'],
            'available_withdraw_amount' => $availableWithdrawAmount,
        );

        if (time() - strtotime($userInfo['login_at']) > 172800) {
            $msg = '！！！请注意！！！收款员【' . $userInfo['username'] . '】时隔2天以上未登录，现已上线，请核实是否本人登录！--' . date('Y-m-d H:i:s');
            Common::telegramSendMsg($msg);
        }

        //更新登录时间
        Cashier::updateAll(array('login_at' => date('Y-m-d H:i:s')), array('username' => $username));

        unset($key);
        unset($redisData);

        return $returnData;
    }


    /**
     * 获取验证码
     * @return   array
     */
    public function actionGetcaptcha()
    {
        //生成uuid, 以此uuid为key， 将验证码保存到redis
        $key = Common::generateUUID();
        $captcha = Common::generateCaptcha();

        \Yii::$app->redis->setex('Captcha_' . $key, 300, $captcha);

        $returnData = Common::ret();
        $returnData['result'] = 1;
        $returnData['msg'] = \Yii::t('app', 'succeed');
        $returnData['data'] = array(
            'captcha_key' => $key,
            'captcha' => $captcha,
        );

        unset($key);
        unset($captcha);
        return $returnData;
    }


    //退出登录
    public function actionLogout()
    {
        //设置默认JSON格式返回数据
        \Yii::$app->response->format = 'json';

        $returnData = Common::ret();

        //传递过来的值
        $data = \Yii::$app->request->bodyParams;
        $token = isset($data['token']) && $data['token'] ? $data['token'] : '';
        if (!$token) {
            $returnData['result'] = 0;
            $returnData['msg'] = \Yii::t('app/error', 'param_error');
            return $returnData;
        }
        if ($token && \Yii::$app->redis->get('User_' . $token)) {
            \Yii::$app->redis->del('User_' . $token);
        }

        $returnData['result'] = 1;
        $returnData['msg'] = \Yii::t('app', 'succeed');
        return $returnData;
    }


    /**
     * 获取app相关信息
     * @params  int  $app_type   app类型 :  1 android   2 ios
     * @return array
     */
    public function actionGetappinfo()
    {

        $returnData = Common::ret();
        $params = \Yii::$app->request->bodyParams;

        if (!($params && isset($params['app_type']) && is_numeric($params['app_type']) && in_array($params['app_type'], array(1, 2)))) {
            $returnData['result'] = 0;
            $returnData['msg'] = \Yii::t('app/error', 'param_error');
            return $returnData;
        }

        $appConfig = SystemConfig::getSystemConfig('APP_CONFIG');
        $appConfig = json_decode($appConfig, true);

        $appInfo = array();

        if ($appConfig) {
            foreach ($appConfig as $config) {
                if (isset($config['app_type']) && $config['app_type'] == $params['app_type']) {
                    $appInfo = $config;
                    break;
                }
            }
        }

        $appVersion = $appInfo && isset($appInfo['app_version']) && $appInfo['app_version'] ? $appInfo['app_version'] : '';
        $downloadUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/site/download';
        $appFileName = $appInfo && isset($appInfo['app_file_name']) && $appInfo['app_file_name'] ? $appInfo['app_file_name'] : '';
        $updateMsg = $appInfo && isset($appInfo['update_msg']) && $appInfo['update_msg'] ? $appInfo['update_msg'] : '';

        $backendDomain = SystemConfig::getSystemConfig('Backend_Domain');

        $returnData['result'] = 1;
        $returnData['msg'] = \Yii::t('app', 'succeed');
        $returnData['data'] = array(
            'app_version' => $appVersion,
            'download_url' => $downloadUrl,
            //'app_file' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/download/' . $appFileName,
            'app_file' => $backendDomain . '/download/' . $appFileName,
            'update_msg' => $updateMsg,
        );

        return $returnData;

    }


    //测试消息推送
    public function actionTestmsg()
    {
        $params = \Yii::$app->request->bodyParams;

        $ret['type'] = 'msg';
        $ret['data'] = $params['msg'];
        $ret = json_encode($ret, 256);

        Gateway::sendToUid($params['username'] . '[cashier]', $ret);

        return 'success';

    }


    /**
     * 充值回调
     */
    public function actionDepositNotify()
    {

        \Yii::$app->response->format = 'json';

        $ori_params = \Yii::$app->request->rawBody;
        $params = json_decode($ori_params, true);
        \Yii::info(json_encode(array('ori' => $ori_params, 'decode' => $params), 256), 'deposit_notify_origin_params');

        $returnData = array('code' => 0, 'msg' => '');

        if (!$params) {

            \Yii::info($ori_params, 'deposit_notify_params_error_1');

            $returnData['msg'] = '参数错误_1';
            return $returnData;
        }


        /*$redisLockKey = 'deposit_notify_'.$params['merchant_id'];
        if(Common::redisLock($redisLockKey) === false){
            $returnData['result'] = 0;
            $returnData['msg'] = '请勿频繁操作';
            return $returnData;
        }*/


        $transaction = \Yii::$app->db->beginTransaction();

        try {

            //获取并验证重要参数
            $merchantId = isset($params['merchant_id']) && $params['merchant_id'] ? $params['merchant_id'] : '';
            $typayOrderId = isset($params['typay_order_id']) && $params['typay_order_id'] ? $params['typay_order_id'] : '';
            $merchantOrderId = isset($params['merchant_order_id']) && $params['merchant_order_id'] ? $params['merchant_order_id'] : '';
            $payAmount = isset($params['pay_amt']) && is_numeric($params['pay_amt']) && $params['pay_amt'] > 0 ? sprintf('%.2f', $params['pay_amt']) : '0.00';
            $paytype = isset($params['pay_type']) && is_numeric($params['pay_type']) && $params['pay_type'] > 0 && intval($params['pay_type']) == $params['pay_type'] ? $params['pay_type'] : 0;
            $payMessage = isset($params['pay_message']) && is_numeric($params['pay_message']) && $params['pay_message'] > 0 ? $params['pay_message'] : 0;
            $bankCode = isset($params['bank_code']) && $params['bank_code'] ? $params['bank_code'] : '';
            $remark = isset($params['remark']) && $params['remark'] ? $params['remark'] : '';
            $sign = isset($params['sign']) && $params['sign'] ? $params['sign'] : '';

            if (!($merchantId && $typayOrderId && $merchantOrderId && $payAmount > 0 && $paytype > 0 && $sign && $payMessage > 0 && $bankCode && $remark)) {

                $transaction->rollback();

                \Yii::info($ori_params, 'deposit_notify_params_error_2');

                $returnData['code'] = 0;
                $returnData['msg'] = '参数错误_2';
                return $returnData;
            }


            //验签
            $signStr = 'merchant_id=' . $merchantId . '&merchant_order_id=' . $merchantOrderId . '&typay_order_id=' . $typayOrderId . '&pay_type=' . $paytype . '&pay_amt=' . $payAmount . '&pay_message=' . $payMessage . '&bank_code=' . $bankCode . '&remark=' . $remark . '&key=' . \Yii::$app->params['merchant_key'];
            \Yii::info($signStr, 'deposit_notify_sign_str_1');
            $mySign = md5($signStr);


            if ($sign != $mySign) {

                $transaction->rollback();

                \Yii::info($ori_params, 'deposit_notify_sign_error_1');

                $returnData['code'] = 0;
                $returnData['msg'] = '签名错误_1';
                return $returnData;
            }

            //请求查单接口
            $postParams = array(
                'merchant_id' => \Yii::$app->params['merchant_id'],
                'merchant_order_id' => $merchantOrderId,
                'remark' => $remark,
                'sign' => md5('merchant_id=' . \Yii::$app->params['merchant_id'] . '&merchant_order_id=' . $merchantOrderId . '&key=' . \Yii::$app->params['merchant_key']),
            );
            $orderData = Common::sendRequest(\Yii::$app->params['typay_deposit_url'] . '/deposit/view', $postParams);
            \Yii::info($orderData, 'typay_order_origin_data');
            $orderData = json_decode($orderData, true);

            //获取订单必要信息
            $merchantId = $orderData && isset($orderData['merchant_id']) && $orderData['merchant_id'] ? $orderData['merchant_id'] : '';
            $merchantOrderId = $orderData && isset($orderData['merchant_order_id']) && $orderData['merchant_order_id'] ? $orderData['merchant_order_id'] : '';
            $typayOrderId = $orderData && isset($orderData['typay_order_id']) && $orderData['typay_order_id'] ? $orderData['typay_order_id'] : '';
            $paytype = $orderData && isset($orderData['pay_type']) && $orderData['pay_type'] ? $orderData['pay_type'] : '';
            $payAmount = $orderData && isset($orderData['pay_amt']) && $orderData['pay_amt'] ? sprintf('%.2f', $orderData['pay_amt']) : '';
            $paidAmount = $orderData && isset($orderData['pay_paid_amt']) && $orderData['pay_paid_amt'] ? sprintf('%.2f', $orderData['pay_paid_amt']) : '';
            $payMessage = $orderData && isset($orderData['pay_message']) && $orderData['pay_message'] ? $orderData['pay_message'] : '';
            $remark = $orderData && isset($orderData['remark']) && $orderData['remark'] ? $orderData['remark'] : '';
            $sign = $orderData && isset($orderData['sign']) && $orderData['sign'] ? $orderData['sign'] : '';

            if (!($merchantId && $merchantOrderId && $typayOrderId && $paytype && $payAmount && $paidAmount && $payMessage && $remark && $sign)) {

                $transaction->rollback();

                \Yii::info($ori_params, 'deposit_notify_params_error_3');

                $returnData['code'] = 0;
                $returnData['msg'] = '参数错误_3';
                return $returnData;
            }

            //再验签
            $mySign = md5('merchant_id=' . $merchantId . '&merchant_order_id=' . $merchantOrderId . '&typay_order_id=' . $typayOrderId . '&pay_type=' . $paytype . '&pay_amt=' . $payAmount . '&pay_paid_amt=' . $paidAmount . '&pay_message=' . $payMessage . '&remark=' . $remark . '&key=' . \Yii::$app->params['merchant_key']);

            if ($sign != $mySign) {

                $transaction->rollback();

                \Yii::info($ori_params, 'deposit_notify_sign_error_2');

                $returnData['code'] = 0;
                $returnData['msg'] = '签名错误_2';
                return $returnData;
            }

            //查询本方订单信息
            $where = array(
                ':system_deposit_id' => $merchantOrderId,
                ':out_deposit_id' => $typayOrderId,
                ':pay_type' => $paytype,
                ':expire_time' => date('Y-m-d H:i:s', strtotime('-180 mins')),
            );
            $myOrderData = Deposit::find()->where("system_deposit_id=:system_deposit_id and out_deposit_id=:out_deposit_id and pay_type=:pay_type and deposit_status in (0,1) and insert_at >= :expire_time", $where)->asArray()->one();

            \Yii::info($ori_params . '--' . json_encode($myOrderData, 256), 'deposit_notify_myorder');

            if (!$myOrderData) {

                $transaction->rollback();

                \Yii::info($ori_params . '--' . json_encode($myOrderData, 256), 'deposit_notify_no_match_order');

                $returnData['code'] = 0;
                $returnData['msg'] = '没有匹配的订单';
                return $returnData;
            }


            //@todo  后台配置开启浮动金额匹配的充值方式， 每个充值方式还要配置上下浮动范围


            //浮动金额匹配
            $minRange = -50; //浮动范围
            $maxRange = 50; //浮动范围

            //金额差值
            $diffAmount = $paidAmount - $myOrderData['deposit_money'];

            if (!($diffAmount >= $minRange && $diffAmount <= $maxRange)) {

                $transaction->rollback();

                \Yii::info($ori_params . '--' . json_encode($myOrderData, 256) . '--' . "diff:{$diffAmount}, min:{$minRange}, max:{$maxRange}", 'deposit_notify_amount_not_match');

                $returnData['code'] = 0;
                $returnData['msg'] = '订单金额与支付金额不匹配';
                return $returnData;
            }

            //匹配成功, 开启事务执行后续操作（修改订单信息、用户上分、记录明细）

            //更新订单中的相关信息
            if (Deposit::updateAll(array('pay_amount' => $paidAmount, 'deposit_status' => Deposit::$OrderStatusSucceed, 'update_at' => date('Y-m-d H:i:s')), array('id' => $myOrderData['id'], 'deposit_status'=> array(0, 1))) === false) {
                $transaction->rollback();
                throw new \Exception('更新订单信息失败:' . $myOrderData['id']);
            }

            //写入资金交易明细
            if (!FinanceDetail::financeCalc($myOrderData['username'], FinanceDetail::$FinanceTypeMargin, $paidAmount, FinanceDetail::$UserTypeCashier, '存款==》保证金变动')) {
                $transaction->rollback();
                throw new \Exception('写入存款资金交易明细失败:' . $myOrderData['id']);
            }

            //更新收款员余额
            if (!Cashier::updateCashierBalance($myOrderData['username'], $paidAmount, 'security_money')) {
                $transaction->rollback();
                throw new \Exception('更新余额失败:' . $myOrderData['id']);
            }

            $transaction->commit();


            //返回响应信息
            $returnData['code'] = 200;
            $returnData['msg'] = '成功';

            return $returnData;

        } catch (\Exception $e) {

            $transaction->rollback();

            \Yii::error(json_encode(array('msg' => $e->getMessage(), 'params' => $params), 256), 'deposit_notify_exception');
            $returnData['code'] = 0;
            $returnData['msg'] = 'error';
            return $returnData;
        }
        return $returnData;
    }


    /**
     * 取款回调
     */
    public function actionWithdrawNotify()
    {

        \Yii::$app->response->format = 'json';

        $ori_params = \Yii::$app->request->rawBody;
        $params = json_decode($ori_params, true);
        \Yii::info(json_encode(array('ori' => $ori_params, 'decode' => $params), 256), 'withdraw_notify_origin_params');

        $returnData = array('code' => 0, 'msg' => '');

        if (!$params) {

            \Yii::info($ori_params, 'withdraw_notify_params_error_1');

            $returnData['msg'] = '参数错误_1';
            return $returnData;
        }


        $redisLockKey = 'withdraw_notify_'.$params['merchant_id'];
        if(Common::redisLock($redisLockKey) === false){
            $returnData['result'] = 0;
            $returnData['msg'] = '请勿频繁操作';
            return $returnData;
        }


        $transaction = \Yii::$app->db->beginTransaction();

        try {

            //获取并验证重要参数
            $merchantId = isset($params['merchant_id']) && $params['merchant_id'] ? $params['merchant_id'] : '';
            $typayOrderId = isset($params['typay_order_id']) && $params['typay_order_id'] ? $params['typay_order_id'] : '';
            $merchantOrderId = isset($params['merchant_order_id']) && $params['merchant_order_id'] ? $params['merchant_order_id'] : '';
            $payAmount = isset($params['pay_amt']) && is_numeric($params['pay_amt']) && $params['pay_amt'] > 0 ? sprintf('%.2f', $params['pay_amt']) : '0.00';
            $paytype = isset($params['pay_type']) && is_numeric($params['pay_type']) && $params['pay_type'] > 0 && intval($params['pay_type']) == $params['pay_type'] ? $params['pay_type'] : 0;
            $payMessage = isset($params['pay_message']) && is_numeric($params['pay_message']) && $params['pay_message'] > 0 ? $params['pay_message'] : 0;
            $bankCode = isset($params['bank_code']) && $params['bank_code'] ? $params['bank_code'] : '';
            $remark = isset($params['remark']) && $params['remark'] ? $params['remark'] : '';
            $sign = isset($params['sign']) && $params['sign'] ? $params['sign'] : '';

            if (!($merchantId && $typayOrderId && $merchantOrderId && $payAmount > 0 && $paytype > 0 && $sign && $payMessage > 0 && $bankCode)) {

                $transaction->rollback();

                \Yii::info($ori_params, 'withdraw_notify_params_error_2');

                $returnData['code'] = 0;
                $returnData['msg'] = '参数错误_2';
                return $returnData;
            }


            //验签
            $signStr = 'merchant_id=' . $merchantId . '&merchant_order_id=' . $merchantOrderId . '&typay_order_id=' . $typayOrderId . '&pay_type=' . $paytype . '&pay_amt=' . $payAmount . '&pay_message=' . $payMessage . '&remark=' . $remark . '&key=' . \Yii::$app->params['merchant_key'];
            \Yii::info($signStr, 'withdraw_notify_sign_str_1');
            $mySign = md5($signStr);


            if ($sign != $mySign) {

                $transaction->rollback();

                \Yii::info($ori_params, 'withdraw_notify_sign_error_1');

                $returnData['code'] = 0;
                $returnData['msg'] = '签名错误_1';
                return $returnData;
            }

            //请求查单接口
            $postParams = array(
                'merchant_id' => \Yii::$app->params['merchant_id'],
                'merchant_order_id' => $merchantOrderId,
                'remark' => $remark,
                'sign' => md5('merchant_id=' . \Yii::$app->params['merchant_id'] . '&merchant_order_id=' . $merchantOrderId . '&key=' . \Yii::$app->params['merchant_key']),
            );
            $orderData = Common::sendRequest(\Yii::$app->params['typay_deposit_url'] . '/withdraw/view', $postParams);
            \Yii::info($orderData, 'typay_withdraw_order_origin_data');
            $orderData = json_decode($orderData, true);

            //获取订单必要信息
            $merchantId = $orderData && isset($orderData['merchant_id']) && $orderData['merchant_id'] ? $orderData['merchant_id'] : '';
            $merchantOrderId = $orderData && isset($orderData['merchant_order_id']) && $orderData['merchant_order_id'] ? $orderData['merchant_order_id'] : '';
            $typayOrderId = $orderData && isset($orderData['typay_order_id']) && $orderData['typay_order_id'] ? $orderData['typay_order_id'] : '';
            $paytype = $orderData && isset($orderData['pay_type']) && $orderData['pay_type'] ? $orderData['pay_type'] : '';
            $payAmount = $orderData && isset($orderData['pay_amt']) && $orderData['pay_amt'] ? sprintf('%.2f', $orderData['pay_amt']) : '';
            $paidAmount = $orderData && isset($orderData['pay_paid_amt']) && $orderData['pay_paid_amt'] ? sprintf('%.2f', $orderData['pay_paid_amt']) : '';
            $payMessage = $orderData && isset($orderData['pay_message']) && $orderData['pay_message'] ? $orderData['pay_message'] : '';
            $remark = $orderData && isset($orderData['remark']) && $orderData['remark'] ? $orderData['remark'] : '';
            $sign = $orderData && isset($orderData['sign']) && $orderData['sign'] ? $orderData['sign'] : '';

            if (!($merchantId && $merchantOrderId && $typayOrderId && $paytype && $payAmount && $paidAmount && $payMessage && $sign)) {

                $transaction->rollback();

                \Yii::info($ori_params, 'withdraw_notify_params_error_3');

                $returnData['code'] = 0;
                $returnData['msg'] = '参数错误_3';
                return $returnData;
            }

            //再验签
            $mySign = md5('merchant_id=' . $merchantId . '&merchant_order_id=' . $merchantOrderId . '&typay_order_id=' . $typayOrderId . '&pay_type=' . $paytype . '&pay_amt=' . $payAmount . '&pay_message=' . $payMessage . '&remark=' . $remark . '&key=' . \Yii::$app->params['merchant_key']);

            if ($sign != $mySign) {

                $transaction->rollback();

                \Yii::info($ori_params, 'withdraw_notify_sign_error_2');

                $returnData['code'] = 0;
                $returnData['msg'] = '签名错误_2';
                return $returnData;
            }

            //查询本方订单信息
            $where = array(
                ':system_withdraw_id' => $merchantOrderId,
                ':out_withdraw_id' => $typayOrderId,
                ':withdraw_money' => $paidAmount,
                ':expire_time' => date('Y-m-d H:i:s', strtotime('-180 mins')),
            );
            $myOrderData = Withdraw::find()->where("system_withdraw_id=:system_withdraw_id and out_withdraw_id=:out_withdraw_id and withdraw_status in (0,1) and withdraw_money=:withdraw_money and insert_at >= :expire_time", $where)->asArray()->one();

            \Yii::info($ori_params . '--' . json_encode($myOrderData, 256), 'withdraw_notify_myorder');

            if (!$myOrderData) {

                $transaction->rollback();

                \Yii::info($ori_params . '--' . json_encode($myOrderData, 256), 'withdraw_notify_no_match_order');

                $returnData['code'] = 0;
                $returnData['msg'] = '没有匹配的订单';
                return $returnData;
            }


            //匹配成功, 开启事务执行后续操作（修改订单信息、用户上分、记录明细）

            //更新订单中的相关信息
            if (Withdraw::updateAll(array('withdraw_status' => Withdraw::$OrderStatusSucceed, 'update_at' => date('Y-m-d H:i:s')), array('id' => $myOrderData['id'], 'withdraw_status'=> array(0, 1))) === false) {
                $transaction->rollback();
                throw new \Exception('更新订单信息失败:' . $myOrderData['id']);
            }


            /*//写入资金交易明细--取款
            if (!FinanceDetail::financeCalc($myOrderData['username'], FinanceDetail::$FinanceTypeWithdraw, abs($myOrderData['withdraw_money']) * (-1), FinanceDetail::$UserTypeCashier, '提现')) {
                $transaction->rollback();
                throw new \Exception('写入提现交易明细失败:' . $myOrderData['id']);
            }

            //写入资金交易明细--取款手续费
            if (isset($myOrderData['handling_fee']) && $myOrderData['handling_fee'] > 0) {
                if (!FinanceDetail::financeCalc($myOrderData['username'], FinanceDetail::$FinanceTypeHandlingFee, abs($myOrderData['handling_fee']) * (-1), FinanceDetail::$UserTypeCashier, '提现手续费')) {
                    $transaction->rollback();
                    throw new \Exception('写入提现手续费交易明细失败:' . $myOrderData['id']);
                }
            }*/

            $transaction->commit();


            //返回响应信息
            $returnData['code'] = 200;
            $returnData['msg'] = '成功';

            return $returnData;

        } catch (\Exception $e) {

            $transaction->rollback();

            \Yii::error(json_encode(array('msg' => $e->getMessage(), 'params' => $params), 256), 'withdraw_notify_exception');
            $returnData['code'] = 0;
            $returnData['msg'] = 'error';
            return $returnData;
        }
        return $returnData;
    }


    /**
     * 获取充值、取款配置开关(人工还是自动)
     */
    public function actionGetDepositWithdrawConfig(){

        \Yii::$app->response->format = 'json';

        //$apiDomain = SystemConfig::getSystemConfig('ApiDomain');

        $depositSwitchConfig = SystemConfig::getSystemConfig('auto_deposit_switch');
        $depositSwitchConfig = is_numeric($depositSwitchConfig) && in_array($depositSwitchConfig, array(0, 1)) ? $depositSwitchConfig : 0;
        $depositUrl = $depositSwitchConfig ? '/cashier/deposit-new' : '/cashier/deposit';


        $withdrawSwitchConfig = SystemConfig::getSystemConfig('auto_withdraw_switch');
        $withdrawSwitchConfig = is_numeric($withdrawSwitchConfig) && in_array($withdrawSwitchConfig, array(0, 1)) ? $withdrawSwitchConfig : 0;
        $withdrawUrl = $withdrawSwitchConfig ? '/cashier/withdraw-new' : '/cashier/withdraw';

        $returnData = Common::ret();
        $returnData['result'] = 1;
        $returnData['data'] = array(
            'deposit_config'=>$depositSwitchConfig,
            'deposit_url' =>$depositUrl,
            'withdraw_config'=>$withdrawSwitchConfig,
            'withdraw_url'=>$withdrawUrl,
        );

        return $returnData;

    }


    //支付宝好友红包功能心跳接口
    public function actionAlipayRedEnvelopPulse(){

        \Yii::$app->response->format = 'json';

        $returnData = array('code' => 0, 'msg' => '');

        $ori_params = \Yii::$app->request->rawBody;
        $params = json_decode($ori_params, true);
        \Yii::info(json_encode(array('ori' => $ori_params, 'decode' => $params), 256), 'alipay_redenvelop_pulse_params');

        try {

            if (!$params) {
                $returnData['msg'] = '参数错误_1';
                return $returnData;
            }

            $alipayAccount = isset($params['alipay_account']) && $params['alipay_account'] ? trim($params['alipay_account']) : '';
            $uid = isset($params['uid']) && $params['uid'] && is_numeric($params['uid']) && strlen($params['uid']) == 16 ? trim($params['uid']) : 0;

            if (!($alipayAccount && $uid)) {
                $returnData['msg'] = '支付宝账号或UID参数错误';
                return $returnData;
            }

            //如果心跳还在， 则顺延
            $cacheData = \Yii::$app->redis->get("AlipayRedEnvelopPulse_{$uid}");
            $pulseCacheTime = SystemConfig::getSystemConfig('AlipayRedEnvelopPulseTime');
            $pulseCacheTime = is_numeric($pulseCacheTime) && $pulseCacheTime > 0 ? intval($pulseCacheTime) : 20;
            if ($cacheData) {
                \Yii::$app->redis->setex("AlipayRedEnvelopPulse_{$uid}", $pulseCacheTime, 1);
                $returnData['code'] = 1;
                $returnData['msg'] = '心跳正常';
                return $returnData;
            }


            //心跳不在的， 验证支付宝账号是否存在于系统中
            $alipayAccountInfo = QrCode::find(['qr_account' => $alipayAccount, 'qr_type' => QrCode::$QrTypeAlipay])->asArray()->one();

            if (!$alipayAccountInfo) {
                $returnData['msg'] = '支付宝账号不存在';
                return $returnData;
            }

            if (isset($alipayAccountInfo['qr_status']) && is_numeric($alipayAccountInfo['qr_status']) && in_array($alipayAccountInfo['qr_status'], [QrCode::$QrStatusOff, QrCode::$QrStatusDeleted])) {
                $returnData['msg'] = '账号暂不可用';
                return $returnData;
            }


            //如果系统中的uid与接口参数中的uid不一致， 以接口中的为准，将两者同步一致
            if (!($alipayAccountInfo['alipay_uid'] && $alipayAccountInfo['alipay_uid'] == $uid)) {
                if (QrCode::updateAll(['alipay_uid' => $uid], ['qr_account' => $alipayAccount, 'qr_type' => QrCode::$QrTypeAlipay]) === false) {
                    $returnData['msg'] = '同步UID失败';
                    return $returnData;
                }
            }

            //保存心跳
            \Yii::$app->redis->setex("AlipayRedEnvelopPulse_{$uid}", $pulseCacheTime, 1);

            $returnData['code'] = 1;
            $returnData['msg'] = 'success';

            return $returnData;

        }catch(Exception $e){
            \Yii::error(json_encode(array('msg' => $e->getMessage(), 'params' => $params), 256), 'alipay_redenvelop_pulse_exception');
            $returnData['code'] = 0;
            $returnData['msg'] = 'error';
            return $returnData;
        }

        return $returnData;
    }


    //支付宝好友红包支付回调通知
    public function actionAlipayRedEnvelopConfirm(){
        \Yii::$app->response->format = 'json';

        $returnData = array('code' => 0, 'msg' => '');

        $ori_params = \Yii::$app->request->rawBody;
        $params = json_decode($ori_params, true);
        \Yii::info(json_encode(array('ori' => $ori_params, 'decode' => $params), 256), 'alipay_redenvelop_confirm_params');

        try {

            if (!$params) {
                $returnData['msg'] = '参数错误_1';
                return $returnData;
            }

            //验证参数
            $alipayAccount = isset($params['alipay_account']) && $params['alipay_account'] ? trim($params['alipay_account']) : '';
            $uid = isset($params['uid']) && $params['uid'] && is_numeric($params['uid']) && strlen($params['uid']) == 16 ? trim($params['uid']) : 0;
            $amount = isset($params['amount']) && $params['amount'] && is_numeric($params['amount']) && $params['amount'] > 0 ? trim($params['amount']) : 0;
            $sign = isset($params['sign']) && $params['sign'] ? trim($params['sign']) : '';

            if(!($alipayAccount && $uid && $amount && $sign)){
                $returnData['code'] = 0;
                $returnData['msg'] = '参数错误_2';
                return $returnData;
            }


            $lockKey = $params['uid'] . 'Confirmorder';
            $isContinue = Common::redisLock($lockKey, 3);
            if ($isContinue === false) {
                $returnData['code'] = 0;
                $returnData['msg'] = '操作频繁，请3秒后再试';
                return $returnData;
            }

            //验签 :  签名规则： 固定格式  account=支付宝账号&uid=支付宝UID&amount=金额&key=密钥， 组成字符串后再整体md5
            $mySign = md5("account={$alipayAccount}&uid={$uid}&amount={$amount}&key=".$this->alipayRedEnvelopKey);

            if($mySign != $sign){
                $returnData['code'] = 0;
                $returnData['msg'] = '签名错误';
                return $returnData;
            }


            //匹配订单
            $orders = Order::find()
                ->andWhere(['>=', 'expire_time', 0])
                ->andWhere(['qr_code' => ($alipayAccount) . '_0' . QrCode::$QrTypeAlipay, 'order_amount' => $amount, 'order_type' => QrCode::$QrTypeAlipay, 'order_status' => 1, 'is_settlement' => 0])
                ->asArray()->all();


            if (!$orders) {
                $returnData['code'] = 0;
                $returnData['msg'] = '未匹配到订单';
                return $returnData;
            }

            \Yii::info(json_encode($orders), 'alipay_redenvelop_orders');

            if (count($orders) > 1) {
                $returnData['code'] = 0;
                $returnData['msg'] = '匹配到多条订单， 请手动处理';
                return $returnData;
            }

            $order = $orders[0];

            $res = Order::orderOk($order['id'], '支付宝好友红包自动匹配', 0, 2);
            \Yii::info(json_encode($res), 'alipay_redenvelop_confirm_success_' . $order['order_id']);

            $returnData['code'] = 1;
            $returnData['msg'] = 'success';

            return $returnData;

        }catch(Exception $e){
            \Yii::error(json_encode(array('msg' => $e->getMessage(), 'params' => $params), 256), 'alipay_redenvelop_confirm_exception');
            $returnData['code'] = 0;
            $returnData['msg'] = 'error';
            return $returnData;
        }

        return $returnData;
    }


    /**
     * 获取配置- 注册时是否需要邀请码
     * @return array
     */
    public function actionGetRegisterInviteCodeConfig(){
        \Yii::$app->response->format = 'json';

        $returnData = Common::ret();

        $ignoreRegisterInviteCode = SystemConfig::getSystemConfig('IGNORE_REGISTER_INVITE_CODE');

        $returnData['result'] = 1;
        $returnData['msg'] = 'success';
        $returnData['data'] = array(
            'invite_code_required' => is_numeric($ignoreRegisterInviteCode) && $ignoreRegisterInviteCode == 1 ? 0 : 1,
        );

        return $returnData;
    }


}