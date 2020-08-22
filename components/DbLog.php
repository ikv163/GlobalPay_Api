<?php
namespace components;

use app\common\Common;
use Yii;
use yii\helpers\Url;
use app\models\LogRecord;
/**
 * db变动记录
 *
 * @author      Qimi
 * @copyright   Copyright (c) 2017
 * @version     V1.0
 */
class DbLog
{
    // 日志表名称
    const DB_TABLE_LOG = 'log_record';
    /**
     * 修改操作前.
     */
    public static function beforeUpdate($event)
    {
        //f101bc27b8e5384b395728831b269a38

        //var_dump(\Yii::$app->redis->get('User_f101bc27b8e5384b395728831b269a38'));exit;



        //if(!empty($event->changedAttributes) && Url::to() != '/site/ajax-login') {
        if(Url::to() != '/api/login') {

            // 内容
            $arr['oldAttributes'] = $event->sender->oldAttributes;
            $description = json_encode($arr, JSON_UNESCAPED_UNICODE);

            // IP
            $ip = Yii::$app->getRequest()->getUserIP();


            //获取已登录的用户名
            $username = '';
            $params = \Yii::$app->request->bodyParams;

            if(isset($params['token']) && $params['token']){
                $cashier = \Yii::$app->redis->get('User_'.$params['token']);
                if($cashier){
                    $cashier = json_decode($cashier, true);
                    if($cashier && isset($cashier['username']) && $cashier['username']){
                        $username = $cashier['username'];
                    }
                }
            }


            // 保存
            $data = ['route' => Url::to(), 'info' => $description . 'ip:' . $ip, 'username' => $username, 'user_type' => 3, 'log_time' => date('Y-m-d H:i:s')];
            $model = new LogRecord();
            $model->setAttributes($data);
            $model->save(false);
        }

    }
    /**
     * 修改操作.
     */
    public static function afterUpdate($event)
    {

        if(!empty($event->changedAttributes) && Url::to() != '/api/login') {
            // 内容
            $arr['changedAttributes'] = $event->changedAttributes;
            $arr['oldAttributes'] = [];
            foreach($event->sender as $key => $value) {
                $arr['oldAttributes'][$key] = $value;
            }
            $description = json_encode($arr,JSON_UNESCAPED_UNICODE);

            // IP转换
            $ip = Yii::$app->getRequest()->getUserIP();


            //获取已登录的用户名
            $username = '';
            $params = \Yii::$app->request->bodyParams;

            if(isset($params['token']) && $params['token']){
                $cashier = \Yii::$app->redis->get('User_'.$params['token']);
                if($cashier){
                    $cashier = json_decode($cashier, true);
                    if($cashier && isset($cashier['username']) && $cashier['username']){
                        $username = $cashier['username'];
                    }
                }
            }


            // 保存
            $data = ['route' => Url::to(), 'info' => $description . 'ip:' . $ip, 'username' => $username, 'user_type' => 3, 'log_time' => date('Y-m-d H:i:s')];
            $model = new LogRecord();
            $model->setAttributes($data);
            $model->save(false);
        }
    }

    /**
     * 删除操作.
     */
    public static function afterDelete($event)
    {
        // 内容
        $arr = [];
        foreach($event->sender as $key => $value) {
            $arr[$key] = $value;
        }
        $description = json_encode($arr,JSON_UNESCAPED_UNICODE);

        // IP转换
        //$ip = Yii::$app->getRequest()->getUserIP();
        $ip = Common::getClientIp();

        //获取已登录的用户名
        $username = '';
        $params = \Yii::$app->request->bodyParams;

        if(isset($params['token']) && $params['token']){
            $cashier = \Yii::$app->redis->get('User_'.$params['token']);
            if($cashier){
                $cashier = json_decode($cashier, true);
                if($cashier && isset($cashier['username']) && $cashier['username']){
                    $username = $cashier['username'];
                }
            }
        }


        // 保存
        $data = ['route' => Url::to(), 'info' => $description . 'ip:' . $ip, 'username' => $username, 'user_type' => 3, 'log_time' => date('Y-m-d H:i:s')];
        $model = new LogRecord();
        $model->setAttributes($data);
        $model->save(false);
    }

    /**
     * 插入操作.
     */
    public static function afterInsert($event)
    {
        if($event->sender->tableName() != self::DB_TABLE_LOG && Url::to() != '/api/login'){
            // 内容
            $arr = [];
            foreach($event->sender as $key => $value) {
                $arr[$key] = $value;
            }
            $description = json_encode($arr,JSON_UNESCAPED_UNICODE);

            // IP转换
            $ip = Yii::$app->getRequest()->getUserIP();

            //获取已登录的用户名
            $username = '';
            $params = \Yii::$app->request->bodyParams;

            if(isset($params['token']) && $params['token']){
                $cashier = \Yii::$app->redis->get('User_'.$params['token']);
                if($cashier){
                    $cashier = json_decode($cashier, true);
                    if($cashier && isset($cashier['username']) && $cashier['username']){
                        $username = $cashier['username'];
                    }
                }
            }


            // 保存
            $data = ['route' => Url::to(), 'info' => $description . 'ip:' . $ip, 'username' => $username, 'user_type' => 3, 'log_time' => date('Y-m-d H:i:s')];
            $model = new LogRecord();
            $model->setAttributes($data);
            $model->save(false);
        }
    }

}