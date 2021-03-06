<?php

namespace app\controllers;

use app\common\Common;
use app\models\Cashier;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\InviteReg;
use app\models\SystemConfig;
use app\common\DES;
use app\models\Deposit;

class SiteController extends Controller
{
    public function init()
    {
        header("Access-Control-Allow-Origin: *");
        parent::init(); // TODO: Change the autogenerated stub
    }

    public function beforeAction($action)
    {
        $this->layout = 'main_1'; //your layout name

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


        \Yii::info(json_encode($logData, 256), 'site_init_log');


        return parent::beforeAction($action);
    }


    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['captcha'], // 授权验证码访问
                        'allow' => true,
                        'roles' => ['?'],
                    ],

                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'minLength' => 4,
                'maxLength' => 4,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }


    /**
     * 推广链接注册
     * @params  $p  string  推广码
     * @return  mixed
     */
    public function actionReg()
    {
        //$this->layout = false;

        $model = new InviteReg();
        $inviteCode = \Yii::$app->request->get('p');
        \Yii::info($inviteCode, 'site/reg-params');

        try {
            if (!$inviteCode) {
                $model->addError('invite_code', \Yii::t('app/error', 'invalid_invite_code'));
                return $this->render('reg', [
                    'model' => $model,
                    'invite_code' => $inviteCode,
                    'error' => 1
                ]);
            }

            if ($model->load(\Yii::$app->request->post())) {

                $newCashier = array();

                //获取推广用户信息
                $parent = Cashier::find()->select('username, agent_class')->where('invite_code=:invite_code', array(':invite_code' => $model->invite_code))->asArray()->one();

                //验证上级用户名及代理等级
                if (!($parent && isset($parent['username']) && $parent['username'] && isset($parent['agent_class']) && is_numeric($parent['agent_class']) && intval($parent['agent_class']) == $parent['agent_class'] && $parent['agent_class'] >= 1)) {

                    \Yii::error(json_encode($parent, 256), 'site/reg-parentError');

                    $model->addError('invite_code', \Yii::t('app/error', 'invalid_invite_code'));
                    return $this->render('reg', [
                        'model' => $model,
                        'invite_code' => $inviteCode,
                        'error' => 1
                    ]);
                }

                //验证用户名是否可用
                if (Cashier::find()->select('username')->where('username=:username', array(':username' => $model->username))->asArray()->one()) {
                    $model->addError('username', \Yii::t('app/error', 'username_exists'));
                    return $this->render('reg', [
                        'model' => $model,
                        'invite_code' => $inviteCode,
                        'error' => 1
                    ]);
                }


                $newCashier['username'] = $model->username;
                $newCashier['login_password'] = md5($model->login_password);
                $newCashier['pay_password'] = '';
                $newCashier['wechat_rate'] = 0;
                $newCashier['alipay_rate'] = 0;
                $newCashier['union_pay_rate'] = 0;
                $newCashier['bank_card_rate'] = 0;
                $newCashier['union_pay_amount'] = 0;
                $newCashier['bank_card_amount'] = 0;
                $newCashier['parent_name'] = $parent['username'];
                $newCashier['insert_at'] = date('Y-m-d H:i:s');
                $newCashier['invite_code'] = Cashier::generateCashierInviteCode();
                $newCashier['cashier_status'] = 1;
                $newCashier['agent_class'] = intval($parent['agent_class']) + 1;

                $newCashierModel = new Cashier($newCashier);

                if ($newCashierModel->save()) {
                    //注册成功， 跳转到app下载页
                    $this->redirect('download');
                } else {
                    $model->addError('error', \Yii::t('app/error', 'register_failed'));
                    return $this->render('reg', [
                        'model' => $model,
                        'invite_code' => $inviteCode,
                        'error' => 1
                    ]);
                }
            }

            return $this->render('reg', [
                'model' => $model,
                'invite_code' => $inviteCode,
                'error' => 0
            ]);
        } catch (\Exception $e) {
            \Yii::error($e->getMessage(), 'site/reg-error');
            $model->addError('error', $e->getMessage());
        }

        return $this->render('reg', [
            'model' => $model,
            'invite_code' => $inviteCode,
            'error' => 1
        ]);

    }


    /**
     * app 下载页
     */
    public function actionDownload()
    {

        $appConfig = SystemConfig::getSystemConfig('APP_CONFIG');
        $appConfig = json_decode($appConfig, true);

        $androidAppInfo = array();
        $iosAppInfo = array();

        if ($appConfig) {
            foreach ($appConfig as $config) {
                if (isset($config['app_type']) && $config['app_type'] == 1) {
                    $androidAppInfo = $config;
                }

                if (isset($config['app_type']) && $config['app_type'] == 2) {
                    $iosAppInfo = $config;
                }
            }
        }

        $androidAppFileName = $androidAppInfo && isset($androidAppInfo['app_file_name']) ? $androidAppInfo['app_file_name'] : '';
        $iosAppFileName = $iosAppInfo && isset($iosAppInfo['app_file_name']) ? $iosAppInfo['app_file_name'] : '';

        $backendDomain = SystemConfig::getSystemConfig('Backend_Domain');
        //\Yii::$app->redis->set('config_Backend_Domain', 'http://backend.glpay.test');

        return $this->render('download', [
            'android_app_filename' => $androidAppFileName,
            'ios_app_filename' => $iosAppFileName,
            'backend_domain' => $backendDomain,
        ]);
    }


    /**
     * 收银台
     */
    public function actionCounter(){

        $dataKey = \Yii::$app->request->get('data', '');

        \Yii::info($dataKey, 'deposit_counter_data_key');

        $params = array(
            'bankcard_num' => '',   //收款卡号
            'owner' => '',          //收款卡持卡人姓名
            'address' => '',        //收款卡开户地址
            'amount' => 0,          //充值金额
            'bank_name'=>'',        //银行名称
            'msg' => '',
            'count_down_time' => 0,
        );

        if(!$dataKey){
            $params['msg'] = '参数data错误';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //解密， 获取存款订单的自增id
        $des = new DES(\Yii::$app->params['counter_key'], 'DES-CBC', DES::OUTPUT_BASE64);
        $orderId = $des->decrypt($dataKey);


        \Yii::info($orderId, 'deposit_counter_orderId');

        if(!(is_numeric($orderId) && $orderId > 0 && intval($orderId) == $orderId)){
            $params['msg'] = '订单id错误';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //查询存款订单数据
        $orderInfo = Deposit::find()->where('id=:id', array(':id'=>$orderId))->asArray()->one();

        \Yii::info(json_encode($orderInfo, 256), 'deposit_counter_db_order');

        if(!$orderInfo){
            $params['msg'] = '订单不存在';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //验证订单状态，是否为待支付(创建状态)
        if(!($orderInfo && isset($orderInfo['deposit_status']) && is_numeric($orderInfo['deposit_status']) && $orderInfo['deposit_status'] == Deposit::$OrderStatusInit)){
            $params['msg'] = '订单状态错误';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //验证订单是否超时
        $expireTime = 1800;
        if((strtotime($orderInfo['insert_at']) + $expireTime) <= time()){
            $params['msg'] = '订单超时_1';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //加入倒计时时间
        $params['count_down_time'] = (strtotime($orderInfo['insert_at']) + $expireTime) - time();


        //取redis中存取的收款渠道信息
        $redisData = \Yii::$app->redis->get('deposit_channel_data_'.$orderId);
        \Yii::info(json_encode(array('order'=>$orderInfo, 'redis_data'=>$redisData),256), 'deposit_counter_redis_data');
        if(!$redisData){
            $params['msg'] = '订单超时_2';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        $channelData = json_decode($redisData, true);

        //校验银行卡号及持卡人姓名
        if(!($channelData && isset($channelData['bank']) && is_numeric($channelData['bank']) && isset($channelData['ownerName']) && $channelData['ownerName'])){
            $params['msg'] = '收款渠道信息错误';
            return $this->render('counter', [
                'params' => $params,
            ]);
        }

        //根据bank_code 获取银行名称
        if($channelData && isset($channelData['bank_code']) && $channelData['bank_code']){
            $bankTypes = \Yii::t('app', 'BankTypes');
            if($bankTypes){
                foreach($bankTypes as $key=>$bankType){
                    if(strtoupper($channelData['bank_code']) == strtoupper($key)){
                        $params['bank_name'] = $bankType['BankTypeName'];
                        break;
                    }
                }
            }
        }

        $params['bankcard_num'] = $channelData['bank'];
        $params['owner'] = $channelData['ownerName'];
        $params['amount'] = $orderInfo['deposit_money'];
        $params['address'] = $channelData && isset($channelData['address']) && $channelData['address'] ? $channelData['address'] : '';
        $params['msg'] = 1;

        return $this->render('counter', [
            'params' => $params,
        ]);
    }


    public function actionGetGoogleKey(){
        $ga = new \PHPGangsta_GoogleAuthenticator();
        $googleKey = $ga->createSecret();
        echo $googleKey;exit;
    }
}
