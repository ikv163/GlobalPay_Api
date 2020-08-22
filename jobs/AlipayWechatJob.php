<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use app\models\Order;
use app\models\TransactionFlow;
use yii\base\BaseObject;

class AlipayWechatJob extends BaseObject implements \yii\queue\RetryableJobInterface
{
    public $trans_id;

    public function execute($queue)
    {
        \Yii::info($this->trans_id, 'AlipayWechatJob_Begin');
        try {
            $trans = TransactionFlow::find()->where(['trans_id' => $this->trans_id, 'trans_status' => 0])->one();
            if (!$trans) {
                \Yii::info($this->trans_id . '不存在', 'AlipayWechatJob_noExsit');
                return false;
            }

            //匹配订单
            $orders = Order::find()
                ->where(['<', 'insert_at', $trans->trans_time])
                ->andWhere(['>=', 'expire_time', $trans->trans_time])
                ->andWhere(['qr_code' => ($trans->trans_account) . '_0' . ($trans->trade_cate), 'order_amount' => $trans->trans_amount, 'order_type' => $trans->trade_cate, 'order_status' => 1, 'is_settlement' => 0])
                ->asArray()->all();

            if (!$orders) {
                $trans->read_remark = '流水未匹配到订单';
                $trans->trans_status = 3;
                $trans->update_at = date('Y-m-d H:i:s');
                $trans->save();
                return false;
            }

            \Yii::info(json_encode($orders), 'AlipayWechatJob_Orders');

            if (count($orders) > 1) {
                $trans->read_remark = '流水对应多笔订单，请手动操作';
                $trans->update_at = date('Y-m-d H:i:s');
                $trans->save();
                return false;
            }

            $order = $orders[0];

            $trans->read_remark = $order['order_id'];
            $trans->trans_status = 2;
            $trans->update_at = $trans->pick_at = date('Y-m-d H:i:s');
            $trans->save();

            $res = Order::orderOk($order['id'], '自动匹配', 0, 2);
            \Yii::info(json_encode($res), 'AlipayWechatJob_Result_' . $order['order_id']);
            if ($res['result'] == 0) {
                throw new \Exception('匹配操作失败');
            }
        } catch (\Exception $e) {
            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'YinshangJob_error');
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