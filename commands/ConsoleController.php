<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\common\Common;
use app\jobs\MsgJob;
use app\jobs\OrderCalcIncomeJob;
use app\jobs\OrderNotifyJob;
use app\models\Cashier;
use app\models\FinanceDetail;
use app\models\Order;
use app\models\QrCode;
use app\models\SystemConfig;
use Yii;
use yii\console\Controller;

/**
 * This command echoes the first argument that you have entered.
 *
 * This command is provided as an example for you to learn how to create console commands.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ConsoleController extends Controller
{
    /**
     * 超时订单
     */
    public function actionOvertime()
    {
        try {
            $orders = Order::find()->where(['<', 'expire_time', date('Y-m-d H:i:s')])->andWhere(['order_status' => 1])->limit(500)->all();
            if ($orders) {
                foreach ($orders as $k => $v) {
                    $v->order_status = 3;
                    $v->update_at = date('Y-m-d H:i:s');
                    //订单设置成超时状态
                    $orderRes = $v->save();
                    if (!$orderRes) {
                        continue;
                    }

                    //返还用户的可收额度
                    if ($v->order_type == 1) {
                        $qr_type_amount = 'alipay_amount';
                        $financeType = ['type' => 13, 'name' => '订单超时，返还支付宝可收额度' . $v->order_id];
                    } else if ($v->order_type == 2) {
                        $qr_type_amount = 'wechat_amount';
                        $financeType = ['type' => 9, 'name' => '订单超时，返还微信可收额度' . $v->order_id];
                    } else if ($v->order_type == 3) {
                        $qr_type_amount = 'union_pay_amount';
                        $financeType = ['type' => 15, 'name' => '订单超时，返还云闪付可收额度' . $v->order_id];
                    } else if ($v->order_type == 4) {
                        $qr_type_amount = 'bank_card_amount';
                        $financeType = ['type' => 18, 'name' => '订单超时，返还银行卡可收额度' . $v->order_id];
                    }
                    $cashierResult = Cashier::updateAllCounters([
                        $qr_type_amount => $v->order_amount,
                    ], ['username' => $v->username]);
                    if (!$cashierResult) {
                        continue;
                    }
                    $detailCashierRes = FinanceDetail::financeCalc($v->username, $financeType['type'], $v->order_amount, 3, $financeType['name']);

                    //系列操作是否都成功
                    if ($orderRes && $detailCashierRes && $cashierResult) {
                        //二维码接单失败笔数
                        $qrKey = $v->qr_code . date('YmdHi') . 'failed';
                        //用户接单失败笔数
                        $userKey = $v->username . date('YmdHi') . 'failed';

                        $qrFailedTimes = Yii::$app->redis->get($qrKey);
                        $qrFailedTimes = $qrFailedTimes != false ? $qrFailedTimes + 1 : 1;
                        Yii::$app->redis->setex($qrKey, '86400', $qrFailedTimes);

                        $userFailedTimes = Yii::$app->redis->get($userKey);
                        $userFailedTimes = $userFailedTimes != false ? $userFailedTimes + 1 : 1;
                        Yii::$app->redis->setex($userKey, '86400', $userFailedTimes);
                    } else {
                        Common::telegramSendMsg($v->order_id . '订单 =>' . $orderRes . '收款员明细 =>' . $detailCashierRes . '收款员=>' . $cashierResult);
                    }
                }
            }

            $qrCodeOrderTimesLimit = json_decode(SystemConfig::getSystemConfig('QrCodeOrderTimesLimit'), 1);
            $time = date('Y-m-d H:i:s', time() - ($qrCodeOrderTimesLimit['time'] * 60));

            $all = QrCode::find()->where(['=', 'qr_status', 2])->count('id');
            $used = QrCode::find()->where(['>', 'last_code_time', $time])->andWhere(['=', 'qr_status', 2])->count('id');
            $avg = $used / $all * 100;
            if ($avg >= 80) {
                Common::telegramCashierInfo('全体注意！' . '%0A%0A' . '后台接单二维码个数 ' . $all . ' 个' . '%0A%0A' . '已使用 ' . $used . '个' . '%0A%0A' . '使用率已超过80%，请运营人员处理！');
            }

        } catch (\Exception $e) {
            \Yii::info($e->getMessage() . '-' . $e->getLine() . '-' . $e->getFile(), 'Api_OverTime4');
        }
    }

    /**
     * 订单成功，但未通知或未结算，则加入队列去通知和结算
     */
    public function actionValidateOrderIsDone()
    {
        try {
            $noNotify = Order::find()->where(['in', 'order_status', [2, 5]])->andWhere(['notify_status' => 1])->andWhere(['>=', 'insert_at', date('Y-m-d')])->limit(100)->orderBy(['id' => SORT_ASC])->all();
            Yii::info(count($noNotify), 'Console_ValidateOrderIsDoneNotify');
            if ($noNotify) {
                //回调通知
                foreach ($noNotify as $v) {
                    if (!(Yii::$app->redis->get('noNotify' . $v->id))) {
                        Yii::$app->qmqueue->push(new OrderNotifyJob(['id' => $v->id]));
                        Yii::$app->redis->setex('noNotify' . $v->id, '86400', 1);
                    }
                }
            }
            $noIncome = Order::find()->where(['in', 'order_status', [2, 5]])->andWhere(['is_settlement' => 0])->andWhere(['>=', 'insert_at', date('Y-m-d')])->limit(100)->orderBy(['id' => SORT_ASC])->all();
            Yii::info(count($noIncome), 'Console_ValidateOrderIsDoneIncome');
            if ($noIncome) {
                //结算佣金
                foreach ($noIncome as $v) {
                    if (!(Yii::$app->redis->get('noIncome' . $v->id))) {
                        Yii::$app->qmqueue->push(new OrderCalcIncomeJob(['username' => $v->username, 'order_id' => $v->order_id, 'order_type' => $v->order_type]));
                        Yii::$app->redis->setex('noIncome' . $v->id, '86400', 1);
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::error($e->getFile() . '-' . $e->getMessage() . '-' . $e->getLine(), 'Console_ValidateOrderIsDone_error');
        }
    }

    /**
     * 不符合接单要求的二维码检查。一旦发现后二维码设为启用状态，不准接单
     */
    public function actionValidateQrcode()
    {
        try {
            //全部接单的二维码
            $qrcodes = QrCode::find()->where(['qr_status' => 2])->limit(5)->all();
            \Yii::info(count($qrcodes), 'Console_ValidateQrcode');
            if (!$qrcodes) {
                return false;
            }

            //几分钟内连续或总超时失败多少笔订单则不准接单--后台配置
            $qrCodeOvertimeOrders = json_decode(SystemConfig::getSystemConfig('QrCodeOvertimeOrders'), 1);
            \Yii::info(json_encode($qrCodeOvertimeOrders, 256), 'Console_ValidateQrcode_config');

            foreach ($qrcodes as $qrcode) {
                //先判断二维码是否符合要求
                $res = QrCode::qrcodeIsOk($qrcode);
                if ($res !== true) {
                    $qrcode->qr_status = 1;
                    $qrcode->update_at = date('Y-m-d H:i:s');
                    $msg = '所属于【' . $qrcode->username . '】的二维码【' . $qrcode->qr_code . '】：已被系统停止接单【' . $res . '】';
                    if ($qrcode->save()) {
                        Yii::$app->qmqueue->push(new MsgJob(['msg' => $msg, 'username' => $qrcode->username]));
                    }
                    Yii::info($msg, 'Console_ValidateQrcode_bad_' . $qrcode->qr_code);
                    continue;
                }

                if ($qrCodeOvertimeOrders) {
                    //符合基本要求，则判断是否触发后台设置的条件
                    //配置详情
                    $time = $qrCodeOvertimeOrders['time'];
                    $orders = $qrCodeOvertimeOrders['orders'];
                    $isLast = $qrCodeOvertimeOrders['isLast'];
                    $isLastMsg = $isLast == 1 ? '连续' : '总共';

                    $userFlag = 0;//需要统计的时间段，从上一分钟开始
                    $now = date('YmdHi', time() - 60);
                    $now_timestamp = strtotime($now);
                    $nowStatis = $now;//给下面的清除redis数据用
                    $failed = $success = 0;
                    //这里循环获取到指定时间内成功和超时的订单次数
                    while ($userFlag != $time) {
                        if ($isLast == 1) {
                            $failed = $failed + Yii::$app->redis->get($qrcode->qr_code . $now . 'failed');
                            $success = $success + Yii::$app->redis->get($qrcode->qr_code . $now . 'success');
                            if ($success) {
                                $failed = 0;
                            }
                        } else {
                            $failed = $failed + Yii::$app->redis->get($qrcode->qr_code . $now . 'failed');
                            $success = $success + Yii::$app->redis->get($qrcode->qr_code . $now . 'success');
                        }
                        Yii::info([$failed, $success, $now], 'Console_validateQrcode_qrcode_' . $qrcode->qr_code);
                        $userFlag++;
                        $now_timestamp = $now_timestamp - 60;
                        $now = date('YmdHi', $now_timestamp);
                    }
                    Yii::info(json_encode([$qrcode->qr_code, $failed, $success], 256), 'Console_validateQrcode_qrcode_info');

                    //否则如果失败次数大于指定的次数，则修改这个收款员名下的所有二维码
                    if ($failed >= $orders) {
                        $qrcode->qr_status = 1;
                        $qrcode->update_at = date('Y-m-d H:i:s');
                        if ($qrcode->save()) {
                            $msg = '所属于【' . $qrcode->username . '】的二维码【' . $qrcode->qr_code . '】：已被系统停止接单【' . $time . '分钟内' . $isLastMsg . '超时失败' . $orders . '笔或以上的订单】';
                            Yii::info(json_encode(['config' => $qrCodeOvertimeOrders, 'redisFailed' => $failed, 'redisSuccess' => $success], 256), 'QrcodesBad_config');
                            Yii::$app->qmqueue->push(new MsgJob(['msg' => $msg, 'username' => $qrcode->username]));

                            //提示过后，redis缓存的数据情况一下
                            $userFlag = 0;
                            $now_timestamp = strtotime($nowStatis);
                            while ($userFlag != $time) {
                                Yii::$app->redis->del($qrcode->qr_code . $nowStatis . 'failed');
                                Yii::$app->redis->del($qrcode->qr_code . $nowStatis . 'success');
                                $userFlag++;
                                $now_timestamp = $now_timestamp - 60;
                                $nowStatis = date('YmdHi', $now_timestamp);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Yii::info($e->getMessage() . '-' . $e->getFile() . '-' . $e->getLine(), 'Console_ValidateQrcode_exception');
        }
    }

    /**
     * 每小时定时纸飞机发送一级代理团队的成功率
     */
    public function actionCashierInfos()
    {
        Yii::getLogger()->flushInterval = 100;
        $lastTime = Yii::$app->redis->get('CashierInfos');
        Yii::info($lastTime . '-' . time() . '-' . ($lastTime + 3600 > time()), 'CashierInfos');
        if ($lastTime + 3600 > time()) {
            return false;
        }

        $first = Cashier::find()->where(['agent_class' => 1, 'cashier_status' => 1])->select(['username', 'parent_name'])->asArray()->all();
        if (!$first) {
            Common::telegramCashierInfo('当前没有状态正常的一级代理，所以成功率不进行播报！');
        }
        $end = date('Y-m-d H:00:00');
        $start = date('Y-m-d H:00:00', strtotime($end) - 3600);
        $final = [];
        foreach ($first as $k => $v) {
            $team = Cashier::calcTeam($v);
            $team = array_column($team, 'username');
            array_push($team, $v['username']);

            $orders = Order::find()->where(['in', 'username', $team])->andWhere(['>=', 'insert_at', $start])->andWhere(['<', 'insert_at', $end])->asArray()->all();
            unset($team);
            $data['username'] = $v['username'];
            $data['allAmount'] = 0;
            $data['successAmount'] = 0;
            $data['allTimes'] = count($orders);
            $data['successTimes'] = 0;
            if (!$orders) {
                continue;
            }
            foreach ($orders as $order) {
                $data['allAmount'] = $data['allAmount'] + $order['order_amount'];
                if (in_array($order['order_status'], [2, 5])) {
                    $data['successAmount'] = $data['successAmount'] + $order['actual_amount'];
                    $data['successTimes'] = $data['successTimes'] + 1;
                }
            }
            if ($data['allTimes'] == 0) {
                $data['successRate'] = 0;
            } else {
                $data['successRate'] = bcmul(($data['successTimes'] / $data['allTimes']), 100, 3);
            }
            array_push($final, $data);
        }
        Yii::$app->redis->setex('CashierInfos', 86400, strtotime($end));
        array_multisort(array_column($final, 'successRate'), SORT_DESC, $final);
        $time = '【' . $start . '】-【' . $end . '】';
        $str = '';
        $str = $str . $time . '%0A%0A';
        foreach ($final as $item) {
            $str = $str . '一级代理【' . $item['username'] . '】团队接单详情 ==> 总接单金额是：' . $item['allAmount'] . '元，成功接单金额是：' . $item['successAmount'] . '元，总笔数是：' . $item['allTimes'] . '笔，成功笔数是：' . $item['successTimes'] . '笔，成功率是：' . $item['successRate'] . '%' . '%0A%0A';
        }
        $str = $str . $time;
        Common::telegramCashierInfo($str);
        Yii::getLogger()->flush(true);
    }
}
