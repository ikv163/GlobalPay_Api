<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use Yii;
use app\common\Common;
use app\models\Merchant;
use app\models\Order;
use yii\base\BaseObject;

class OrderNotifyJob extends BaseObject implements \yii\queue\RetryableJobInterface
{
    public $id;

    public function execute($queue)
    {
        try {
            Yii::getLogger()->flushInterval = 100;
            $order = Order::findOne($this->id);
            if (!in_array($order->order_status, [2, 4, 5])) {
                throw new \Exception('订单未完成，无法回调');
            }

            $merchant = Merchant::find()->where(['mch_name' => $order->mch_name])->one();

            Yii::info(json_encode([$order->toArray(), $merchant->toArray()], 256), 'OrderNotifyJob_Model');

            $merchant = Merchant::find()->where(['mch_name' => $order->mch_name])->select(['mch_code'])->one();

            $data['mch_order_id'] = $order->mch_order_id;
            $data['mch_code'] = $merchant->mch_code;
            $data['order_type'] = $order->order_type;

            $tempMoney = bcadd($order->actual_amount, $order->benefit, 2);
            if ($order->benefit && ($tempMoney != $order->order_amount)) {
                $data['order_amount'] = $order->actual_amount;
            } elseif ($order->benefit && ($tempMoney == $order->order_amount)) {
                $data['order_amount'] = $order->order_amount;
            } elseif ($order->order_amount != $order->actual_amount) {
                $data['order_amount'] = $order->actual_amount;
            } else {
                $data['order_amount'] = $order->order_amount;
            }

            $data['callback_url'] = $order->callback_url;
            $data['notify_url'] = $order->notify_url;
            $data['order_status'] = $order->order_status;
            $data['id'] = $order->order_id;
            $data['return_date'] = date('Y-m-d H:i:s');

            //密钥
            $sign = Order::validateSign($merchant->mch_code, $data);

            $data['sign'] = $sign['sign'];

            Yii::info(json_encode([$data, $merchant->toArray()], 256), 'OrderNotifyJob_Data');

            $res = Common::curl($order->notify_url, $data);
            Yii::info($res, 'OrderNotifyJob_Res_' . $data['mch_order_id']);
            if ($res == 'success') {
                $order->notify_status = 2;
                $order->update_at = date('Y-m-d H:i:s');
                $order->save();
                return true;
            } else {
                throw new \Exception('发起回调，未收到success信息' . $data['mch_order_id']);
            }
            Yii::getLogger()->flush(true);
        } catch (\Exception $e) {
            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'OrderNotifyJob_Error');
            Yii::getLogger()->flush(true);
            throw new \Exception('回调失败');
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