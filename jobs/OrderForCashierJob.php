<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use app\models\Cashier;
use app\models\Order;
use GatewayWorker\Lib\Gateway;
use yii\base\BaseObject;

class OrderForCashierJob extends BaseObject implements \yii\queue\RetryableJobInterface
{

    public $order_id;

    public function execute($queue)
    {
        return true;
        try {
            $order = Order::find()->where(['order.order_id' => $this->order_id])
                ->leftJoin('qr_code', 'qr_code.username = order.username')
                ->select([
                    'order.order_id',
                    'order.mch_order_id',
                    'order.id',
                    'order.username',
                    'order.qr_code',
                    'order.order_type',
                    'order.order_amount',
                    'order.order_status',
                    'order.expire_time',
                    'order.insert_at',
                    'qr_code.qr_nickname',
                    'qr_code.qr_account',
                ])->one();
            if (!$order) {
                \Yii::error($this->order_id . '不存在', 'OrderForCashierJob_noExsit');
                return false;
            }

            $ret['type'] = 'order';
            $ret['data'] = $order->toArray();
            $ret = json_encode($ret, 256);

            Gateway::sendToUid($order->username . '[cashier]', $ret);

            \Yii::info(json_encode(['data' => $order->toArray(), 'user' => $order->username], 256), 'OrderForCashierJob_success');

        } catch (\Exception $e) {
            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'OrderForCashierJob_error');
        }

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