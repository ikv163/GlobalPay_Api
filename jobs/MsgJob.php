<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use app\models\Cashier;
use GatewayWorker\Lib\Gateway;
use yii\base\BaseObject;

class MsgJob extends BaseObject implements \yii\queue\RetryableJobInterface
{

    public $msg;
    public $username;

    public function execute($queue)
    {
        return true;
//        try {
//            $ret['type'] = 'msg';
//            $ret['data'] = $this->msg;
//            $ret = json_encode($ret, 256);
//
//            Gateway::sendToUid($this->username . '[cashier]', $ret);
//
//            $cashier = Cashier::find()->where(['username' => $this->username])->select(['parent_name'])->one();
//            $parent = null;
//            if ($cashier) {
//                $parent = $cashier->parent_name;
//                Gateway::sendToUid($parent . '[cashier]', $ret);
//            }
//            $list = Gateway::getAllUidList();
//            \Yii::info(json_encode(['data' => $ret, 'user' => $this->username, 'parent' => $parent, 'list' => $list],256), 'MsgJob_success');
//        } catch (\Exception $e) {
//            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'MsgJob_error');
//        }
    }

    /**
     * @return int time to reserve in seconds
     */
    public function getTtr()
    {
        return 10;
    }

    /**
     * @param int $attempt number
     * @param \Exception|\Throwable $error from last execute of the job
     * @return bool
     */
    public function canRetry($attempt, $error)
    {
        return ($attempt < 3);
    }

}