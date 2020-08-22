<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 19:33
 */

namespace app\common;

use app\models\SystemConfig;
use Yii;
use app\common\qrReader\QrReader;
use yii\httpclient\Client;

class Common
{
    //获取 Model 错误信息中的 第一条，无错误时 返回 null

    public static function getModelError($model)
    {
        $errors = $model->getErrors();
        //得到所有的错误信息
        if (!is_array($errors)) {
            return '';
        }
        $firstError = array_shift($errors);
        if (!is_array($firstError)) {
            return '';
        }
        return array_shift($firstError);
    }

    public static function telegramSendMsg($msg)
    {
        $isOk = SystemConfig::getSystemConfig('OpenTelegramInfo');
        if ($isOk == 1) {
            $url = 'https://api.telegram.org/bot1088652490:AAFc749_h7ts4sSCxsTpB3cnDqnZYehQbWY/sendMessage?chat_id=-385498986&text=' . $msg;
            file_get_contents($url);
        }
    }

    public static function telegramCashierInfo($msg)
    {
        $isOk = SystemConfig::getSystemConfig('OpenTelegramInfo');
        if ($isOk == 1) {
            $url = 'https://api.telegram.org/bot1088652490:AAFc749_h7ts4sSCxsTpB3cnDqnZYehQbWY/sendMessage?chat_id=-1001283691027&text=' . $msg;
            file_get_contents($url);
        }
    }

    /**
     * 生成uuid
     */
    public static function generateUUID()
    {
        if (function_exists('com_create_guid')) {
            $str = com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $uuid = substr($charid, 0, 8)
                . substr($charid, 8, 4)
                . substr($charid, 12, 4)
                . substr($charid, 16, 4)
                . substr($charid, 20, 12);

            $str = $uuid;
        }
        return $str;
    }


    /**
     * 生成四位验证码
     */
    public static function generateCaptcha()
    {


        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '1', '2', '3', '4', '5', '6', '7', '8', '9');

        $length = count($chars);

        $captcha = '';
        for ($i = 0; $i < 4; $i++) {
            $key = mt_rand(0, $length - 1);
            $captcha .= $chars[$key];
        }
        return $captcha;
    }

    //返回给APP的格式
    public static function ret()
    {
        return ['result' => 0, 'msg' => '', 'data' => array()];
    }

    /**
     * @param $url 请求网址
     * @param bool $params 请求参数
     * @return bool|mixed
     */
    public static function curl($url, $post_data)
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 1);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置post数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        // 在尝试连接时等待的秒数
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        // 最大执行时间
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }

    /*
     * 二维码是否存在此金额的订单
     * type 1 存redis 0取redis
     * time 0 存永久  大于0 setex
     */
    public static function isQrCodeHasThisMoney($qr_code, $money, $type = 0, $val = 1)
    {
        $keyX = $qr_code . ($money * 100);
        if ($type == 1) {
            $exire = SystemConfig::getSystemConfig('OrderExpireTime');
            $exire = $exire == null ? 5 : $exire;
            \Yii::$app->redis->setex($keyX, $exire * 60, $val);
            return true;
        } else {
            return \Yii::$app->redis->get($keyX);
        }
    }

    /*
     * 二维码当天已收金额
     * type 1 存redis 0取redis
     * time 0 存永久  大于0 setex
     */
    public static function qrTodayMoney($qr_code, $type = 0, $val = 0, $isSuccess = 0)
    {
        if ($isSuccess == 0) {
            $allOrSuccess = 'all';
        } else {
            $allOrSuccess = 'success';
        }
        $today = date('Ymd');
        $keyX = $qr_code . $today . 'money' . $allOrSuccess;
        if ($type == 1) {
            \Yii::$app->redis->setex($keyX, 90000, bcadd(\Yii::$app->redis->get($keyX), $val, 2));
            return true;
        } else {
            $temp = \Yii::$app->redis->get($keyX);
            $temp = $temp == false ? 0 : $temp;
            return $temp;
        }
    }

    /*
     * 二维码当天已收笔数
     * type 1 存redis 0取redis
     * time 0 存永久  大于0 setex
     */
    public static function qrTodayTimes($qr_code, $type = 0, $val = 1, $isSuccess = 0)
    {
        if ($isSuccess == 0) {
            $allOrSuccess = 'all';
        } else {
            $allOrSuccess = 'success';
        }
        $today = date('Ymd');
        $keyX = $qr_code . $today . 'times' . $allOrSuccess;
        if ($type == 1) {
            \Yii::$app->redis->setex($keyX, 90000, \Yii::$app->redis->get($keyX) + $val);
            return true;
        } else {
            $temp = \Yii::$app->redis->get($keyX);
            $temp = $temp == false ? 0 : $temp;
            return $temp;
        }
    }

    /*
     * 收款员当天已收金额
     * type 1 存redis 0取redis
     * time 0 存永久  大于0 setex
     */
    public static function cashierTodayMoney($username, $qr_type, $type = 0, $val = 0, $isSuccess = 0)
    {
        if ($isSuccess == 0) {
            $allOrSuccess = 'all';
        } else {
            $allOrSuccess = 'success';
        }
        $today = date('Ymd');
        $keyX = $username . $qr_type . $today . 'money' . $allOrSuccess;
        if ($type == 1) {
            Yii::info($val . '-' . \Yii::$app->redis->get($keyX), 'ccc');
            \Yii::$app->redis->setex($keyX, 90000, bcadd(\Yii::$app->redis->get($keyX), $val, 2));
            return true;
        } else {
            $temp = \Yii::$app->redis->get($keyX);
            $temp = $temp == false ? 0 : $temp;
            return $temp;
        }
    }

    /*
     * 收款员当天已收笔数
     * type 1 存redis 0取redis
     * time 0 存永久  大于0 setex
     */
    public static function cashierTodayTimes($username, $qr_type, $type = 0, $val = 1, $isSuccess = 0)
    {
        if ($isSuccess == 0) {
            $allOrSuccess = 'all';
        } else {
            $allOrSuccess = 'success';
        }
        $today = date('Ymd');
        $keyX = $username . $qr_type . $today . 'times' . $allOrSuccess;
        if ($type == 1) {
            \Yii::$app->redis->setex($keyX, 90000, \Yii::$app->redis->get($keyX) + $val);
            return true;
        } else {
            $temp = \Yii::$app->redis->get($keyX);
            $temp = $temp == false ? 0 : $temp;
            return $temp;
        }
    }


    /**
     * 获取二维码图片的url链接
     * @param $base64_image_content     string      二维码base64数据
     * @return mixed
     */
    public static function parseBase64DataToUrl($base64_image_content)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = $_SERVER['DOCUMENT_ROOT'] . '/';

            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                @mkdir($new_file, 0700);
            }
            $new_file = $new_file . time() . ".{$type}";
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                $qrRead = new QrReader($new_file);
                $final = $qrRead->text();
                Yii::info($final, 'Common_parseBase64DataToUrlOk');
                return $final;
            } else {
                Yii::info('图片保存失败', 'Common_SaveError');
                return false;
            }
        } else {
            Yii::info('格式匹配失败', 'Common_parseBase64DataToUrl');
            return false;
        }
    }

    public static function uploadBase64Img($base64_image_content)
    {
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            $type = $result[2];
            $new_file = 'image/refund/';

            if (!file_exists($new_file)) {
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                @mkdir($new_file, 0700);
            }
            $new_file = $new_file . time() . ".{$type}";
            if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                return $new_file;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * yii自带httpclient 发送请求
     */
    public static function sendRequest($url, $data, $method = 'post')
    {
        $client = new Client();
        $res = $client->createRequest();
        if (!empty($head)) {
            $res->setHeaders($head);
            $res->setFormat(Client::FORMAT_JSON);
        }
        $res->setMethod($method);
        $res->setUrl($url);
        $res->setData($data);
        $res->setOptions([
            'timeout' => 30,
            CURLOPT_TIMEOUT => 30,
        ]);

        $repose = $res->send();
        return $repose->getContent();
    }

    public static function redisLock($lockKey, $expireTime = 3)
    {
        $isContinue = Yii::$app->redis->setnx($lockKey, 1);
        if ($isContinue != true) {
            return false;
        }
        Yii::$app->redis->expire($lockKey, $expireTime);
        return true;
    }

    /**
     * 获取客户端真实ip
     * @return string
     */
    public static function getClientIp()
    {

        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $cip = $_SERVER["HTTP_CLIENT_IP"];
        } else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (!empty($_SERVER["REMOTE_ADDR"])) {
            $cip = $_SERVER["REMOTE_ADDR"];
        } else {
            $cip = '';
        }
        preg_match("/[\d\.]{7,15}/", $cip, $cips);
        $cip = isset($cips[0]) ? $cips[0] : 'unknown';
        unset($cips);

        return $cip;
    }

}