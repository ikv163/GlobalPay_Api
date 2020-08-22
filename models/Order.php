<?php

namespace app\models;

use app\common\Common;
use app\jobs\OrderCalcIncomeJob;
use app\jobs\OrderForCashierJob;
use app\jobs\OrderNotifyJob;
use Yii;
use yii\db\Expression;
use itbdw\Ip\IpLocation;

/**
 * This is the model class for table "order".
 *
 * @property int $id
 * @property string $order_id 订单ID
 * @property string $mch_order_id 商户订单号
 * @property string $username 收款员
 * @property int $qr_code 收款二维码Code
 * @property string $mch_name 商户用户名
 * @property int $order_type 订单收款码类型 1支付宝 2微信
 * @property float $order_fee 订单手续费
 * @property float $order_amount 订单金额
 * @property float|null $benefit 订单优惠金额
 * @property float $actual_amount 实际订单金额
 * @property string $callback_url 同步回调地址
 * @property string $notify_url 异步回调地址
 * @property int $order_status 订单状态 1未支付 2已支付 3超时 4手动失败 5手动成功
 * @property int $notify_status 通知状态 1未通知 2已通过 3通知失败
 * @property string $expire_time 过期时间
 * @property string|null $read_remark 被读取标识
 * @property string|null $insert_at 创建时间
 * @property string|null $update_at 修改时间
 * @property string|null $operator 订单操作者（后台管理员手动操作订单）
 */
class Order extends \yii\db\ActiveRecord
{

    //定义各订单状态：  1未支付 2已支付 3超时 4手动失败 5手动成功
    public static $OrderStatusUnpaid = 1;
    public static $OrderStatusPaid = 2;
    public static $OrderStatusOvertime = 3;
    public static $OrderStatusMFailed = 4;
    public static $OrderStatusMSucceed = 5;
    public $refund_status;
    public $refund_type;
    public $query_team;

    public static $OrderStatusRel = array(
        '1' => '未支付',
        '2' => '已支付',
        '3' => '超时',
        '4' => '手动失败',
        '5' => '手动成功',
    );


    //定义允许收款员（或其上级代理）确认接单到账的订单状态
    public static $AllowConfirmOrderStatus = array(1);


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_type', 'order_status', 'notify_status', 'refund_status', 'refund_type'], 'integer'],
            [['order_fee', 'order_amount', 'benefit', 'actual_amount'], 'number'],
            [['expire_time', 'insert_at', 'update_at', 'refund_status', 'query_team'], 'safe'],
            [['order_id', 'mch_order_id'], 'string', 'max' => 100],
            [['user_ip'], 'ip'],
            [['username', 'operator', 'qr_code'], 'string', 'max' => 50],
            [['mch_name', 'callback_url', 'notify_url', 'read_remark'], 'string', 'max' => 255],
            [['order_id'], 'required'],
            [['order_id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'order_id' => Yii::t('app', 'Order ID'),
            'mch_order_id' => Yii::t('app', 'Mch Order ID'),
            'username' => Yii::t('app', 'Username'),
            'qr_code' => Yii::t('app', 'Qr Code'),
            'mch_name' => Yii::t('app', 'Mch Name'),
            'order_type' => Yii::t('app', 'Order Type'),
            'user_ip' => Yii::t('app', 'user_ip'),
            'order_fee' => Yii::t('app', 'Order Fee'),
            'order_amount' => Yii::t('app', 'Order Amount'),
            'benefit' => Yii::t('app', 'Benefit'),
            'actual_amount' => Yii::t('app', 'Actual Amount'),
            'callback_url' => Yii::t('app', 'Callback Url'),
            'notify_url' => Yii::t('app', 'Notify Url'),
            'order_status' => Yii::t('app', 'Order Status'),
            'notify_status' => Yii::t('app', 'Notify Status'),
            'expire_time' => Yii::t('app', 'Expire Time'),
            'read_remark' => Yii::t('app', 'Read Remark'),
            'insert_at' => Yii::t('app', 'Insert At'),
            'update_at' => Yii::t('app', 'Update At'),
            'query_team' => Yii::t('app/model', 'query_team'),
            'operator' => Yii::t('app', 'Operator'),
        ];
    }

    /**
     * @param $mch_code
     * @param $data
     * @return string|null
     * 验签
     *
     */
    public static function validateSign($mch_code, $data)
    {
        //通过mch_code查询商户的mch_key
        $merchant = Merchant::find()->where(['mch_code' => $mch_code])->andWhere(['mch_status' => 1])->one();
        if (!$merchant) {
            Yii::error(json_encode($data, 256), 'validateSign_noMerchant');
            return null;
        }
        unset($data['sign']);
        $signStr = '';
        ksort($data);
        foreach ($data as $k => $v) {
            //参数有空值的，直接验签失败
            if ($v == null) {
                Yii::error(json_encode($data, 256), 'validateSign_paramsIsNull');
                return null;
            } else {
                $signStr .= $k . '=' . $v . '&';
            }
        }
        //传递过来的所有参数加上mch_key和当天的日期参与加密
        $signStr .= $merchant->mch_key . '&' . date('Ymd');
        Yii::info(json_encode(['data' => $data, 'signStr' => $signStr], 256), 'validateSign_ok');
        return ['sign' => md5($signStr), 'merchant' => $merchant];
    }

    //获取订单号
    public static function generateOrderId($mch_code)
    {
        return 'QMZF' . strtoupper(substr(md5(microtime() . $mch_code), 8, 16));
    }

    //计算订单的费率
    public static function calcOrderFee($mch_code, $money, $order_type)
    {
        $rate = '';
        if ($order_type == 1) {
            $rate = 'alipay_rate';
        } elseif ($order_type == 2) {
            $rate = 'wechat_rate';
        } elseif ($order_type == 3) {
            $rate = 'union_pay_rate';
        } else {
            $rate = 'bank_card_rate';
        }
        $merchantRate = Merchant::find()->select([$rate])->where(['mch_code' => $mch_code])->one();
        if ($merchantRate->$rate) {
            return bcdiv(bcmul($money, $merchantRate->$rate), 100, 2);
        } else {
            return 0;
        }
    }

    /**
     * @param $originalData
     * @param $merchant
     * @param $qrCode
     * @return bool|string
     * 创建订单
     */
    public static function createOrder($originalData, $merchant, $qrCode)
    {

        \Yii::info(json_encode(array('mch_data' => $originalData, 'mch' => $merchant, 'qrcode' => $qrCode), 256), 'Create_Order_Params');

        try {
            $orderDatas['order_id'] = self::generateOrderId($merchant->mch_code);
            $orderDatas['mch_order_id'] = $originalData['mch_order_id'];
            $orderDatas['username'] = $qrCode->username;
            $orderDatas['qr_code'] = $qrCode->qr_code;
            $orderDatas['mch_name'] = $merchant->mch_name;
            if (in_array($originalData['order_type'], [101, 102, 103, 104])) {
                $tpyeName = [101 => '网银转卡', 102 => '支付宝转卡', 103 => '微信转卡', 104 => '手机号转账'];
                $orderDatas['order_type'] = 4;
                $orderDatas['read_remark'] = $tpyeName[$originalData['order_type']];
            } else {
                $orderDatas['order_type'] = $originalData['order_type'];
            }
            $orderDatas['order_fee'] = self::calcOrderFee($merchant->mch_code, $originalData['order_amount'], $orderDatas['order_type']);
            $orderDatas['order_amount'] = $originalData['order_amount'];
            $orderDatas['benefit'] = 0;
            $orderDatas['user_ip'] = $originalData['user_ip'];
            $orderDatas['actual_amount'] = 0;
            $orderDatas['callback_url'] = $originalData['callback_url'];
            $orderDatas['notify_url'] = $originalData['notify_url'];
            $orderDatas['order_status'] = 1;
            $orderDatas['notify_status'] = 1;
            $expireTime = SystemConfig::getSystemConfig('OrderExpireTime');
            $expireTime = $expireTime == null ? 5 : $expireTime;
            $orderDatas['expire_time'] = date('Y-m-d H:i:s', time() + ($expireTime * 60));
            $order = new Order();
            $order->load($orderDatas, '');
            if (!$order->validate()) {
                Yii::info(json_encode(['data' => $orderDatas, 'msg' => Common::getModelError($order)], 256), 'Order_createOrder_validateError');
                return '数据验证不通过';
            }

            //当前二维码是否已经存在此金额的订单了
            $isHas = Common::isQrCodeHasThisMoney($qrCode->qr_code, $originalData['order_amount']);
            if ($isHas) {
                Yii::info(json_encode($order->toArray(), 256), 'Order_createOrder_alreadyHasTheMoney_' . $originalData['mch_order_id']);
                return '此二维码已存在此金额';
            } else {
                //保存成功则redis记录此二维码已收此金额，指定时间内不再接受此金额
                Common::isQrCodeHasThisMoney($qrCode->qr_code, $originalData['order_amount'], 1, 1);
            }

            $transaction = \Yii::$app->db->beginTransaction();

            //订单入库
            $orderResult = $order->save();

            if (!$orderResult) {
                $transaction->rollBack();
                return '订单入库失败了';
            }

            //修改用户的可收额度
            if ($qrCode->qr_type == 1) {
                $qr_type_amount = 'alipay_amount';
                $financeType = ['type' => 12, 'name' => '接单，支付宝额度减少' . $orderDatas['order_id']];
            } elseif ($qrCode->qr_type == 2) {
                $qr_type_amount = 'wechat_amount';
                $financeType = ['type' => 8, 'name' => '接单，微信额度接单减少' . $orderDatas['order_id']];
            } elseif ($qrCode->qr_type == 3) {
                $qr_type_amount = 'union_pay_amount';
                $financeType = ['type' => 14, 'name' => '接单，云闪付额度接单减少' . $orderDatas['order_id']];
            } elseif ($qrCode->qr_type == 4) {
                $qr_type_amount = 'bank_card_amount';
                $financeType = ['type' => 17, 'name' => '接单，银行卡额度接单减少' . $orderDatas['order_id']];
            }
            $detailCashierRes = FinanceDetail::financeCalc($qrCode->username, $financeType['type'], ($originalData['order_amount'] * -1), 3, $financeType['name']);
            $cashierResult = Cashier::updateAllCounters([
                $qr_type_amount => $originalData['order_amount'] * -1,
            ], [
                'and', ['>=', new Expression("$qr_type_amount - " . $originalData['order_amount']), 0], ['username' => $qrCode->username]
            ]);

            if (!$detailCashierRes || !$cashierResult) {
                $transaction->rollBack();
                return '收款员金额操作失败';
            }
            \Yii::info(json_encode(array('orderDatas' => $orderDatas, 'orderResult' => $orderResult, 'cashierResult' => $cashierResult, 'detailCashierRes' => $detailCashierRes), 256), 'CreateOrder-AllRes');

            if ($orderResult && $cashierResult && $detailCashierRes) {
                //事务提交
                $transaction->commit();
                //websocket推送给收款员
                Yii::$app->qmqueue->push(new OrderForCashierJob(['order_id' => $order->order_id]));
                //记录上次接单的二维码
                Yii::$app->redis->set('last_code' . $order->order_type, $order->qr_code);
                //记录二维码今日接单金额
                Common::qrTodayMoney($qrCode->qr_code, 1, $originalData['order_amount']);
                //记录二维码今日接单数量
                Common::qrTodayTimes($qrCode->qr_code, 1, 1);
                //记录收款员今日接单金额
                Common::cashierTodayMoney($qrCode->username, $qrCode->qr_type, 1, $originalData['order_amount']);
                //记录收款员今日接单笔数
                Common::cashierTodayTimes($qrCode->username, $qrCode->qr_type, 1);
                return true;
            } else {
                $transaction->rollBack();
                Yii::error(json_encode(['cashier' => $cashierResult, 'order' => $orderResult, 'data' => $order->toArray()], 256), 'Order_createOrder_rollback');
                if (!$orderResult) {
                    $msg = '订单数据写入失败';
                } elseif (!$cashierResult) {
                    $msg = '收款员数据写入失败';
                } elseif (!$detailCashierRes) {
                    $msg = '收款员资金交易明细写入失败';
                } else {
                    $msg = '入库时失败';
                }
                return $msg;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error($e->getMessage() . '--' . $e->getLine() . '--' . $e->getFile(), 'Order_createOrder_Error' . $originalData['mch_order_id']);
            return '写入数据库异常';
        }
    }

    /**
     * @param $mch_code
     * @param $mch_order_id
     * @return array|\yii\db\ActiveRecord|null
     * 商户查询订单状态
     */
    public static function queryOrder($mch_code, $mch_order_id)
    {
        $merchant = Merchant::find()->where(['mch_code' => $mch_code])->select(['mch_name'])->one();
        if (!$merchant) {
            return null;
        }
        $order = Order::find()->where(['mch_name' => $merchant->mch_name, 'mch_order_id' => $mch_order_id])->one();

        if (!$order) {
            return null;
        }
        return $order;
    }


    //订单回调
    public static function orderNotify($id)
    {
        try {
            $order = Order::findOne($id);
            if ($order->notify_status != 1) {
                \Yii::info('订单已完成回调_' . $order->order_status, 'Order_orderNotify_Finish');
            }
            if (!in_array($order->order_status, [2, 4, 5])) {
                return '订单未完成，无法回调';
            }

            $merchant = Merchant::find()->where(['mch_name' => $order->mch_name])->one();

            Yii::info(json_encode([$order->toArray(), $merchant->toArray()], 256), 'Order_orderNotify_model');

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

            Yii::info(json_encode([$data, $merchant->toArray()], 256), 'Order_OrderNotify_data');

            $res = Common::curl($order->notify_url, $data);
            Yii::info('mch_order_id:' . $data['mch_order_id'] . '_res:' . $res, 'Order_OrderNotify_Res');
            if ($res == 'success') {
                $order->notify_status = 2;
                $order->update_at = date('Y-m-d H:i:s');
                $order->save();
                return true;
            } else {
                return '发起回调，未收到success信息';
            }
        } catch (\Exception $e) {
            Yii::info($e->getMessage() . '-' . $e->getFile() . '-' . $e->getLine(), 'Order_OrderNotify_Error');
        }
    }

    /**
     * @param $username
     * @param $money
     * @param $rateType
     * 计算佣金
     */
    public static function incomeCalc($username, $orderId, $rateType)
    {
        $incomeCalc_transaction = Yii::$app->db->beginTransaction();
        $first = Cashier::find()->select(['wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'parent_name', 'username'])->where(['username' => $username])->one();
        $order = Order::findOne(['order_id' => $orderId]);

        Yii::info(json_encode(['cashier' => $first->toArray(), 'order' => $order->toArray()], 256), 'Order_incomeCalc1_' . $orderId);

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
        Yii::info($res_mch_balance . '-' . $res_mch_finance, 'Order_incomeCalc_mch_' . $orderId);
        if (!$res_mch_finance || !$res_mch_balance) {
            $incomeCalc_transaction->rollBack();
            return null;
        }

        $next = null;
        try {
            while ($first) {
                Yii::info(json_encode($first->toArray(), 256), 'Order_incomeCalc2_' . $orderId);
                $order_type = $type[$rateType];
                if ($next) {
                    $finalRate = bcsub($first->$order_type, $next->$order_type, 2);
                } else {
                    $finalRate = $first->$order_type;
                }
                $temp = bcmul($finalRate, $money, 2);
                //计算出来的佣金大于0才进行数据库操作和资金交易明细
                if ($temp > 0) {
                    $income = bcdiv($temp, 100, 2);
                    //资金交易明细
                    $resF = FinanceDetail::financeCalc($first->username, 2, $income, 3, $order->order_id . '佣金');
                    $resI = Cashier::updateAllCounters(['income' => $income], ['username' => $first->username]);
                    if (!$resF || !$resI) {
                        Yii::info($resF . '-' . $resI, 'Order_incomeCalc3_' . $orderId);
                        $incomeCalc_transaction->rollBack();
                        return null;
                    }
                }

                if ($first->parent_name) {
                    $next = $first;
                    $first = Cashier::find()->select(['wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'parent_name', 'username'])->where(['username' => $first->parent_name])->one();
                } else {
                    $first = null;
                }
            }
            $order->is_settlement = 1;
            $order->update_at = date('Y-m-d H:i:s');
            if ($order->save()) {
                $incomeCalc_transaction->commit();
                return true;
            } else {
                $incomeCalc_transaction->rollBack();
                return null;
            }
        } catch (\Exception $e) {
            $incomeCalc_transaction->rollBack();
            Yii::error(json_encode(['data' => $first->toArray(), 'msg' => $e->getMessage() . '_' . $e->getLine() . '_' . $e->getFile()], 256), 'Oder_incomeCalc_error_' . $orderId);
            return null;
        }
    }


    /**
     * 收款员确认订单
     * @param $id
     * @return array
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public static function orderOk($id, $loginUsername, $isChangeMoney = 0, $statusType = 5)
    {
        try {
            $transaction = Yii::$app->db->beginTransaction();
            $order = Order::findOne($id);

            Yii::info(json_encode($order->toArray(), 256), 'ApiOrderModel_orderOk_Param_' . $order->mch_order_id);

            if ($order == null) {
                $transaction->rollBack();
                return ['msg' => '订单不存在', 'result' => 0];
            }
            if (in_array($order->order_status, [2, 4, 5])) {
                $transaction->rollBack();
                return ['msg' => '订单已完成', 'result' => 0];
            }
            //记录之前的订单状态
            $beforeStatus = $order->order_status;
            //保持最新的状态-根据传递过来的状态
            $order->order_status = $statusType;

            //如果当前订单没有实到金额，那么订单金额减去优惠就是实到金额
            if ($order->actual_amount == 0) {
                $order->actual_amount = bcsub($order->order_amount, $order->benefit, 2);
            }

            //根据实到金额重新算下订单的手续费
            $merchant = Merchant::findOne(['mch_name' => $order->mch_name]);
            if ($order->order_type == 1) {
                $rate = $merchant->alipay_rate;
            } else if ($order->order_type == 2) {
                $rate = $merchant->wechat_rate;
            } else if ($order->order_type == 3) {
                $rate = $merchant->union_pay_rate;
            } else if ($order->order_type == 4) {
                $rate = $merchant->bank_card_rate;
            }

            $tempMoney = bcadd($order->actual_amount, $order->benefit, 2);
            if ($order->benefit && ($tempMoney != $order->order_amount)) {
                $nowMoney = $order->actual_amount;
            } elseif ($order->benefit && ($tempMoney == $order->order_amount)) {
                $nowMoney = $order->order_amount;
            } elseif ($order->order_amount != $order->actual_amount) {
                $nowMoney = $order->actual_amount;
            } else {
                $nowMoney = $order->order_amount;
            }
            $order->order_fee = bcdiv(bcmul($nowMoney, $rate), 100, 2);

            //记录具体是哪个后台人员操作的订单
            $order->operator = $loginUsername;
            $order->update_at = date('Y-m-d H:i:s');
            $orderRes = $order->save();

            Yii::info($orderRes, 'Order_OrderOkIsOk_' . $order->mch_order_id);

            //默认操作都是通过的（后面事务判断需要用到）
            $detailMerchantRes = $merchantResult = $detailCashierRes = $cashierResult = $detailMerchantRes_c = $merchantResult_c = $detailCashierRes_c = $cashierResult_c = 2;
            //订单修改成功，才进行后续操作
            if ($orderRes) {
                //记录码的上次收款时间
                $qrcode = QrCode::find()->where(['qr_code' => $order->qr_code])->one();
                $qrcode->last_money_time = $order->insert_at;
                $qrcode->save();

                //如果是超时订单，商户提单金额增加，收款员的可收额度减少
                if ($beforeStatus == 3) {
                    //返回用户的可收额度和明细记录
                    if ($order->order_type == 1) {
                        $qr_type_amount = 'alipay_amount';
                        $financeType = ['type' => 12, 'name' => '接单，支付宝额度减少'];
                    } elseif ($order->order_type == 2) {
                        $qr_type_amount = 'wechat_amount';
                        $financeType = ['type' => 8, 'name' => '接单，微信额度减少'];
                    } elseif ($order->order_type == 3) {
                        $qr_type_amount = 'union_pay_amount';
                        $financeType = ['type' => 14, 'name' => '接单，云闪付额度减少'];
                    } elseif ($order->order_type == 4) {
                        $qr_type_amount = 'bank_card_amount';
                        $financeType = ['type' => 17, 'name' => '接单，银行卡额度减少'];
                    }
                    $detailCashierRes = FinanceDetail::financeCalc($order->username, $financeType['type'], ($order->order_amount * -1), 3, $financeType['name']);
                    $cashierResult = Cashier::updateAllCounters([
                        $qr_type_amount => ($order->order_amount * -1),
                    ], ['username' => $order->username]);
                }

                //如果是修改金额的订单，则需要重新计算商户的额度、收款员的额度
                if ($isChangeMoney) {
                    //修改订单金额，最终产生的差额
                    $finalMoney = bcsub($order->actual_amount, $order->order_amount, 2);

                    if ($beforeStatus == 3) {
                        //如果订单之前的状态是超时，那么就要重新把这笔订单金额记录到商户已提单金额里面
                        $moneyX = $order->actual_amount;
                    } else {
                        //如果不是超时订单，那么只需要修改差额即可
                        $moneyX = abs($finalMoney);
                    }

                    /*
                     * 重新计算（根据差额大于0还是小于0来判断实际操作的金额是负数还是正数）
                     */
                    //修改收款员可收额度和明细添加
                    if ($order->order_type == 1) {
                        $qr_type_amount = 'alipay_amount';
                        $financeType = ['type' => 12, 'name' => '接单，支付宝额度减少-稽查'];
                    } elseif ($order->order_type == 2) {
                        $qr_type_amount = 'wechat_amount';
                        $financeType = ['type' => 8, 'name' => '接单，微信额度减少-稽查'];
                    } elseif ($order->order_type == 3) {
                        $qr_type_amount = 'union_pay_amount';
                        $financeType = ['type' => 14, 'name' => '接单，云闪付额度减少-稽查'];
                    } elseif ($order->order_type == 4) {
                        $qr_type_amount = 'bank_card_amount';
                        $financeType = ['type' => 17, 'name' => '接单，银行卡额度减少-稽查'];
                    }
                    $detailCashierRes_c = FinanceDetail::financeCalc($order->username, $financeType['type'], $finalMoney < 0 ? $moneyX : ($moneyX * -1), 3, $financeType['name']);
                    $cashierResult_c = Cashier::updateAllCounters([
                        $qr_type_amount => $finalMoney < 0 ? $moneyX : ($moneyX * -1),
                    ], ['username' => $order->username]);
                }

                Yii::info(json_encode(['订单' => $orderRes, '商户明细' => $detailMerchantRes, '商户' => $merchantResult, '收款员明细' => $detailCashierRes, '收款员' => $cashierResult], 256), 'Order_orderOk2_' . $order->mch_order_id);
                if ($orderRes && $detailMerchantRes && $merchantResult && $detailCashierRes && $cashierResult && $detailMerchantRes_c && $merchantResult_c && $detailCashierRes_c && $cashierResult_c) {
                    $transaction->commit();
                    //回调通知
                    $resNotify = Yii::$app->qmqueue->push(new OrderNotifyJob(['id' => $id]));
                    Yii::info($resNotify, 'NotifyQueueApi');
                    //结算佣金
                    $resIncome = Yii::$app->qmqueue->push(new OrderCalcIncomeJob(['username' => $order->username, 'order_id' => $order->order_id, 'order_type' => $order->order_type]));
                    Yii::info($resIncome, 'IncomeQueueApi');
                    //处理Redis统计
                    //保存成功则redis记录此二维码已收此金额，指定时间内不再接受此金额
                    Common::isQrCodeHasThisMoney($order->qr_code, $order->order_amount, 1, 0);
                    //记录二维码今日接单金额
                    Common::qrTodayMoney($order->qr_code, 1, $order->order_amount, 1);
                    //记录二维码今日接单数量
                    Common::qrTodayTimes($order->qr_code, 1, 1, 1);
                    //记录收款员今日接单金额
                    Common::cashierTodayMoney($order->username, $order->order_type, 1, $order->order_amount, 1);
                    //记录收款员今日接单笔数
                    Common::cashierTodayTimes($order->username, $order->order_type, 1, 1, 1);
                } else {
                    Yii::info(json_encode(['订单' => $orderRes, '商户明细' => $detailMerchantRes, '商户' => $merchantResult, '收款员明细' => $detailCashierRes, '收款员' => $cashierResult], 256), 'Order_orderOk3_' . $order->mch_order_id);
                    $transaction->rollBack();
                    return ['msg' => '操作失败，请联系相关人员', 'result' => 0];
                }
                return ['msg' => '订单已置为成功状态', 'result' => 1];
            } else {
                $transaction->rollBack();
                return ['msg' => '修改订单状态失败', 'result' => 0];
            }
        } catch (\Exception $e) {
            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'OrderOk11_error');
        }
    }

    /**
     * 检测用户ip所在区域是否允许提单
     * @param $userIp
     * @return bool
     */
    public static function checkOrderIPArea($userIp)
    {

        $ipInfo = IpLocation::getLocation($userIp);
        if ($ipInfo && isset($ipInfo['country']) && stripos($ipInfo['country'], '中国') === FALSE) {
            return false;
        }
        return true;
    }


    public $insert_at_start;
    public $insert_at_end;

}
