<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/20
 * Time: 21:04
 */

namespace app\jobs;

use Yii;
use app\models\Cashier;
use app\models\FinanceDetail;
use app\models\Merchant;
use app\models\Order;
use yii\base\BaseObject;

class OrderCalcIncomeJob extends BaseObject implements \yii\queue\RetryableJobInterface
{
    public $username;
    public $order_id;
    public $order_type;

    public function execute($queue)
    {
        try {
            Yii::getLogger()->flushInterval = 100;
            $incomeCalc_transaction = \Yii::$app->db->beginTransaction();
            $first = Cashier::find()->select(['wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'parent_name', 'username'])->where(['username' => $this->username])->one();
            $order = Order::findOne(['order_id' => $this->order_id]);

            Yii::info(json_encode(['cashier' => $first->toArray(), 'order' => $order->toArray()], 256), 'OrderCalcIncomeJob_incomeCalc1_' . $this->order_id);

            if (!$order) {
                return null;
            } elseif ($order->is_settlement == 1) {
                return null;
            } elseif (in_array($order->order_status, [1, 3, 4])) {
                return null;
            }

            if ($order->benefit != 0 && (bcadd($order->actual_amount, $order->benefit, 2) != $order->order_amount)) {
                $money = $order->actual_amount;
            } elseif ($order->benefit != 0 && (bcadd($order->actual_amount, $order->benefit, 2) == $order->order_amount)) {
                $money = $order->order_amount;
            } elseif ($order->order_amount != $order->actual_amount && $order->actual_amount > 0) {
                $money = $order->actual_amount;
            } else {
                $money = $order->order_amount;
            }

            $type = [
                '1' => 'alipay_rate',
                '2' => 'wechat_rate',
                '3' => 'union_pay_rate',
                '4' => 'bank_card_rate',
            ];

            //商户结算
            $mch_money = bcsub($money, $order->order_fee, 2);
            $res_mch_finance = FinanceDetail::financeCalc($order->mch_name, 2, $mch_money, 2, $order->order_id . '成功，商户余额增加');
            $res_mch_balance = Merchant::updateAllCounters(['balance' => $mch_money], ['mch_name' => $order->mch_name]);
            if (!$res_mch_finance || !$res_mch_balance) {
                $incomeCalc_transaction->rollBack();
                throw new \Exception('操作失败0' . '--' . $res_mch_finance . '--' . $res_mch_balance);
            }

            $next = null;
            while ($first) {
                $order_type = $type[$this->order_type];
                if ($next) {
                    $finalRate = bcsub($first->$order_type, $next->$order_type, 2);
                } else {
                    $finalRate = $first->$order_type;
                }
                $temp = bcmul($finalRate, $money, 2);
                //计算出来的佣金大于0才进行数据库操作和资金交易明细
                if ($temp > 0) {
                    $income = bcdiv($temp, 100, 2);
                    if ($income > 0) {
                        //资金交易明细
                        $resF = FinanceDetail::financeCalc($first->username, 2, $income, 3, $order->order_id . '佣金');
                        $resI = Cashier::updateAllCounters(['income' => $income], ['username' => $first->username]);
                        if (!$resF || !$resI) {
                            Yii::info($resF . '-' . $resI . '-' . $income . '-' . $first->username, 'OrderCalcIncomeJob_incomeCalc3_' . $this->order_id);
                            $incomeCalc_transaction->rollBack();
                            throw new \Exception('操作失败1' . '--' . $resF . '--' . $resI);
                        }
                    }
                }

                if ($first->parent_name) {
                    $next = $first;
                    $first = Cashier::find()->select(['wechat_rate', 'alipay_rate', 'parent_name', 'union_pay_rate', 'bank_card_rate', 'username'])->where(['username' => $first->parent_name])->one();
                } else {
                    $first = null;
                }
            }
            $order->is_settlement = 1;
            $order->update_at = date('Y-m-d H:i:s');
            if ($order->save()) {
                $incomeCalc_transaction->commit();
                Yii::getLogger()->flush(true);
                return true;
            } else {
                Yii::info('OrderCalcIncomeJob_incomeCalc99_' . $this->order_id);
                $incomeCalc_transaction->rollBack();
                throw new \Exception('操作失败1111');
            }
        } catch (\Exception $e) {
            $incomeCalc_transaction->rollBack();
            Yii::info($e->getMessage() . '-' . $e->getFile() . '-' . $e->getLine(), 'OrderCalcIncomeJob_incomeCalc3111_' . $this->order_id);
            throw new \Exception('操作失败2');
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