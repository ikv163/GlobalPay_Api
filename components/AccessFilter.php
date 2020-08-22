<?php

namespace app\components;

use app\models\Cashier;
use yii\base\ActionFilter;
use yii\web\Response;
use yii;

/**
 * Class AccessFilter
 * @package app\components
 */
class AccessFilter extends ActionFilter
{
    public function beforeAction($action)
    {
        //设置默认JSON格式返回数据
        $response = \Yii::$app->response;
        $response->format = Response::FORMAT_JSON;

        //传递过来的值
        $data = \Yii::$app->request->bodyParams;
        Yii::info(json_encode([$data, $_POST], 256), 'beforeAction');

        if (!isset($data['userIp']) || $data['userIp'] == null) {
            $data['userIp'] = $_SERVER['REMOTE_ADDR'];
        }

        //token和用户IP必须传递
        if (!isset($data['token']) || $data['token'] == null) {
            Yii::info(json_encode([$data, $_POST], 256), 'beforeActionToken');
            $response->data = ['result' => 0, 'data' => [], 'msg' => '未传递TOKEN'];
            $response->send();
            die();
        }

        //验证登录态及ip
        $userData = \Yii::$app->redis->get('User_' . $data['token']);
        $userData = $userData ? json_decode($userData, true) : array();

        //redis没取到值，则提示重新登录
        if (!($userData && isset($userData['username']) && $userData['username'] && isset($userData['userIp']) && $userData['userIp'])) {
            Yii::info(json_encode($data, 256), 'beforeActionExpired');
            $response->data = ['result' => -1, 'data' => '', 'msg' => \Yii::t('app/error', 'login_expired')];
            $response->send();
            die();
        }

//ip与登录时的不同，则将用户下线
//        if ($data['userIp'] != $userData['userIp']) {
//            \Yii::$app->redis->del($userData['username']);
//            \Yii::$app->redis->del('User_' . $data['token']);
//            $response->data = ['result' => -1, 'data' => '', 'msg' => \Yii::t('app/error', 'ip_unusual')];
//            $response->send();
//            die();
//        }

        //从redis取到值，证明数据存在，同时保存到POST里，继续传递到控制器中使用
        $_POST['username'] = $userData['username'];
        $_POST['userIp'] = $userData['userIp'];
        //这里是统一判断参数不能传空值，同时做trim操作
        foreach ($_POST as $k => $v) {
            $_POST[$k] = trim($v);
        }
        //原始数据处理后再保存到request中返回
        \Yii::$app->request->setBodyParams($_POST);

        //顺延用户登录态
        \Yii::$app->redis->setex('User_' . $data['token'], \Yii::$app->params['Login_Status_Expire_Time'], json_encode($userData));
        return parent::beforeAction($action);
    }

    public function afterAction($action, $result)
    {
        return parent::afterAction($action, $result);
    }
}