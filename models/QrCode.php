<?php

namespace app\models;

use app\common\Common;
use itbdw\Ip\IpLocation;
use Yii;
use yii\db\Expression;

/**
 * This is the model class for table "qr_code".
 *
 * @property int $id
 * @property string $username 二维码所属人（cashier）
 * @property string $qr_code 二维码简码别名
 * @property string $qr_address 二维码地址
 * @property string $qr_nickname 二维码用户昵称
 * @property string $qr_account 二维码实际账号
 * @property float $per_max_amount 每笔最大可收金额
 * @property float $per_min_amount 每笔最小可收金额
 * @property float $per_day_amount 每日可收金额
 * @property int $per_day_orders 每日接单笔数上限
 * @property int $qr_type 二维码类型 1支付宝 2微信
 * @property int $qr_status 二维码状态 0禁用 1可用 2接单 9删除
 * @property int|null $priority 二维码优先收款等级 默认0 越大越优先
 * @property string|null $last_money_time 上次收款时间
 * @property string|null $last_code_time 上次出码时间
 * @property string|null $control 控制权 username代表用户   平台   自由
 * @property int $is_shopowner 是否为店长二维码 1店长码 2店员码
 * @property string|null $qr_location 二维码所在地
 * @property string|null $qr_relation 关联的店员码qr_code（只有店长码才能进行关联）
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 修改时间
 */
class QrCode extends \yii\db\ActiveRecord
{

    //定义二维码类型  1支付宝  2微信  3云闪付 4银行卡
    public static $QrTypeAlipay = 1;
    public static $QrTypeWechat = 2;
    public static $QrTypeUnionPay = 3;
    public static $QrTypeBankCard = 4;

    public static $QrTypeRel = array(
        '1' => '支付宝',
        '2' => '微信',
        '3' => '云闪付',
        '4' => '银行卡',
    );


    //定义二维码状态  0禁用  1可用  2接单   9删除
    public static $QrStatusOff = 0;
    public static $QrStatusOn = 1;
    public static $QrStatusOrder = 2;
    public static $QrStatusDeleted = 9;

    public static $QrStatusRel = array(
        '0' => '禁用',
        '1' => '可用',
        '2' => '接单',
        '9' => '删除',
    );


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'qr_code';
    }

    public $wechat_amount;
    public $alipay_amount;
    public $cashier_status;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['qr_address', 'qr_nickname', 'qr_account', 'qr_type'], 'required'],
            [['per_max_amount', 'per_min_amount', 'wechat_amount', 'alipay_amount', 'per_day_amount'], 'number'],
            [['per_day_orders', 'qr_type', 'qr_status', 'priority', 'is_shopowner', 'cashier_status'], 'integer'],
            [['qr_type'], 'integer', 'integerOnly' => true, 'min' => 1],
            [['last_money_time', 'last_code_time', 'insert_at', 'update_at', 'real_name', 'telephone', 'bank_card_number', 'bank_code', 'bank_address'], 'safe'],
            [['username', 'qr_code', 'qr_nickname', 'qr_account', 'control', 'qr_location', 'qr_relation', 'alipay_uid'], 'string', 'max' => 50],
            [['qr_address', 'qr_remark'], 'string', 'max' => 255],
            [['qr_code'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/model', 'ID'),
            'username' => Yii::t('app/model', 'Username'),
            'qr_code' => Yii::t('app/model', 'Qr Code'),
            'qr_address' => Yii::t('app/model', 'Qr Address'),
            'qr_nickname' => Yii::t('app/model', 'Qr Nickname'),
            'qr_account' => Yii::t('app/model', 'Qr Account'),
            'per_max_amount' => Yii::t('app/model', 'Per Max Amount'),
            'per_min_amount' => Yii::t('app/model', 'Per Min Amount'),
            'per_day_amount' => Yii::t('app/model', 'Per Day Amount'),
            'per_day_orders' => Yii::t('app/model', 'Per Day Orders'),
            'qr_type' => Yii::t('app/model', 'Qr Type'),
            'qr_status' => Yii::t('app/model', 'Qr Status'),
            'priority' => Yii::t('app/model', 'Priority'),
            'last_money_time' => Yii::t('app/model', 'Last Money Time'),
            'last_code_time' => Yii::t('app/model', 'Last Code Time'),
            'control' => Yii::t('app/model', 'Control'),
            'is_shopowner' => Yii::t('app/model', 'Is Shopowner'),
            'qr_location' => Yii::t('app/model', 'Qr Location'),
            'qr_relation' => Yii::t('app/model', 'Qr Relation'),
            'insert_at' => Yii::t('app/model', 'Insert At'),
            'update_at' => Yii::t('app/model', 'Update At'),
            'qr_remark' => Yii::t('app/model', 'qr_remark'),
            'alipay_uid' => Yii::t('app/model', 'alipay_uid'),
            'real_name' => Yii::t('app/model', 'real_name'),
            'telephone' => Yii::t('app/model', 'telephone'),
            'bank_card_number' => Yii::t('app/model', 'bank_card_number'),
            'bank_code' => Yii::t('app/model', 'bank_code'),
            'bank_address' => Yii::t('app/model', 'bank_address'),
        ];
    }

    /**
     * @param $model
     * @return bool|string
     * 判断二维码是否可以开启接单状态
     */
    public static function qrcodeIsOk($model)
    {
        try {
            if ($model->qr_type == 3) {
                $maxMinMoney = json_decode(SystemConfig::getSystemConfig('UnionPay_PerMaxMin'), 1);
            } else {
                $maxMinMoney = json_decode(SystemConfig::getSystemConfig('PerMaxMin'), 1);
            }
            if ($maxMinMoney == null) {
                return '系统未配置每笔最大最小金额，请联系平台';
            }
            if (empty($model->qr_address)) {
                return '二维码图片未上传';
            } elseif ($model->per_max_amount > $maxMinMoney['max'] || $model->per_min_amount < $maxMinMoney['min']) {
                return '单笔最大、最小可收金额不符合系统要求-【' . $maxMinMoney['min'] . '/' . $maxMinMoney['max'] . '】' . '【' . $model->per_min_amount . '/' . $model->per_max_amount . '】';
            } elseif ($model->per_day_amount <= 0) {
                return '每日可收金额填写错误';
            } elseif ($model->per_day_orders <= 0) {
                return '每日可收订单填写错误';
            } elseif ($model->qr_status == 0 || $model->qr_status == 9) {
                return '禁用或删除状态的二维码不允许接单';
            } elseif ($model->username) {
                if ($model->qr_type == 1) {
                    $type = 'alipay_amount';
                    $rate = 'alipay_rate';
                    $typeName = '支付宝';
                } elseif ($model->qr_type == 2) {
                    $type = 'wechat_amount';
                    $rate = 'wechat_rate';
                    $typeName = '微信';
                } elseif ($model->qr_type == 3) {
                    $type = 'union_pay_amount';
                    $rate = 'union_pay_rate';
                    $typeName = '云闪付';
                } elseif ($model->qr_type == 4) {
                    $type = 'bank_card_amount';
                    $rate = 'bank_card_rate';
                    $typeName = '银行卡';
                }
                $res = Cashier::find()->where(['username' => $model->username])->one();
                if ($res->$type < $maxMinMoney['min']) {
                    return $typeName . '可收额度不足';
                }
                if ($res->$rate <= 0) {
                    return $typeName . '费率未设置';
                }
            } elseif (empty($model->username)) {
                return '二维码无所属人';
            }
            return true;
        } catch (\Exception $e) {
            return '此二维码异常，不允许接单';
        }
    }

    /**
     * @param $data
     * @return array|\yii\db\ActiveRecord[]|null
     * 选码(出码核心逻辑，减少数据库访问，保证快速出码)
     */
    public static function selectQrCode($data)
    {
        try {
            $money = $data['order_amount'];
            $order_type = $data['order_type'];
            if (in_array($data['order_type'], [101, 102, 103, 104])) {
                $order_type = 4;
            }
            $qrCodeRules = SystemConfig::getSystemConfig('QrCodeRules');
            //没有取到配置的出码规则，则默认使用二维码出码时间轮询规则【1】
            $qrCodeRules = $qrCodeRules == null ? 1 : $qrCodeRules;
            $seletedQr = null;
            switch ($qrCodeRules) {
                case 1: {
                    if ($order_type == 1) {
                        $temp_type = '`cashier`.`alipay_amount`';
                    } elseif ($order_type == 2) {
                        $temp_type = '`cashier`.`wechat_amount`';
                    } elseif ($order_type == 3) {
                        $temp_type = '`cashier`.`union_pay_amount`';
                    } elseif ($order_type == 4) {
                        $temp_type = '`cashier`.`bank_card_amount`';
                    }
                    $seletedQr = null;
                    if ($order_type == 4) {
                        //如果是银行卡订单，先找符合要求的渠道，再从渠道中找卡
                        $channel = PayChannel::find();
                        $channel->where(['pay_type' => $data['order_type']])
                            ->andWhere(['channel_status'=> 1])
                            ->andWhere(new Expression($money . ' >= `per_min_amount`'))
                            ->andWhere(new Expression($money . ' <= `per_max_amount`'));
                        if (isset($data['user_credit_level']) && !empty($data['user_credit_level'])) {
                            $channel->andWhere(new Expression('FIND_IN_SET("' . $data['user_credit_level'] . '",credit_level)'));
                        } else {
                            //$channel->andWhere(new Expression('FIND_IN_SET(' . $data['user_level']  . ',user_level)'));

                            //对应收银台那边的用户等级(0=>不限用户等级， 999=>用户等级0)
                            if(isset($data['user_level']) && is_numeric($data['user_level']) && $data['user_level'] != 0){
                                $channel->andWhere(new Expression('FIND_IN_SET(' . $data['user_level']  . ',user_level)'));
                            }
                        }
                        $finalChannel = $channel->one();
                        if (!$finalChannel) {
                            return ['msg' => '没有符合要求的渠道', 'result' => 0];
                        }

                        $qrCodes = PayChannelRelation::find()->where(['channel_id'=>$finalChannel->id])->select(['qr_code'])->asArray()->all();
                        if (!$qrCodes) {
                            return ['msg' => '渠道中未找到码信息', 'result' => 0];
                        }
                        $seletedQr = QrCode::find()
                            ->where(['is_shopowner' => 1, 'qr_status' => 2, 'qr_type' => $order_type])
                            ->andWhere(new Expression($money . ' >= `per_min_amount`'))
                            ->andWhere(new Expression($money . ' <= `per_max_amount`'))
                            ->andWhere(new Expression($money . ' <= ' . $temp_type))
                            ->andWhere(['cashier.cashier_status' => 1])
                            ->andWhere(['in', 'qr_code', $qrCodes])
                            ->select('qr_code.*,cashier.alipay_amount,cashier.wechat_amount,cashier.union_pay_amount,cashier.bank_card_amount,cashier.cashier_status')
                            ->leftJoin('cashier', 'qr_code.username = cashier.username')
                            ->limit(300)
                            ->orderBy(['qr_code.priority' => SORT_DESC, 'qr_code.last_code_time' => SORT_ASC])
                            ->all();
                    } else {
                        $seletedQr = QrCode::find()
                            ->where(['is_shopowner' => 1, 'qr_status' => 2, 'qr_type' => $order_type])
                            ->andWhere(new Expression($money . ' >= `per_min_amount`'))
                            ->andWhere(new Expression($money . ' <= `per_max_amount`'))
                            ->andWhere(new Expression($money . ' <= ' . $temp_type))
                            ->andWhere(['cashier.cashier_status' => 1])
                            ->select('qr_code.*,cashier.alipay_amount,cashier.wechat_amount,cashier.union_pay_amount,cashier.bank_card_amount,cashier.cashier_status')
                            ->leftJoin('cashier', 'qr_code.username = cashier.username')
                            ->limit(300)
                            ->orderBy(['qr_code.priority' => SORT_DESC, 'qr_code.last_code_time' => SORT_ASC])
                            ->all();
                    }
                    break;
                };
                default:
                    break;
            }

            //没有符合添加的码可用
            if ($seletedQr == null) {
                return ['msg' => '没有符合条件的二维码可用', 'result' => 0];
            }
            //找出基本符合要求的二维码
            Yii::info(count($seletedQr), 'selectQrCodeBefore_' . $data['mch_order_id']);

            //记录筛选出去的二维码
            $notOk = [];
            //查看这些码是否能符合其他要求
            $close = [];


            //如果是支付宝订单， 取出后台配置的支付宝好友红包功能开关配置
            $alipayRedEnvelopSwitch = 0;
            if($order_type == 1){
                $alipayRedEnvelopSwitch = SystemConfig::getSystemConfig('AlipayRedEnvelopSwitch');
            }

            foreach ($seletedQr as $k => $v) {
                //此收款员是否可接单 0可以   1不可以
                if (Yii::$app->redis->get('canOrder' . $v->username) == 1) {
                    unset($seletedQr[$k]);
                    $notOk['cashierForbbiden_' . $v->qr_code] = $v;
                    array_push($close, $v->id);
                    Yii::info(json_encode($v->toArray(), 256), 'cashierForbbiden_' . $data['mch_order_id']);
                    continue;
                }

                //是否超过每日可收金额
                $todayTotalMoney = Common::qrTodayMoney($v->qr_code, 0, 0, 1);
                if ($todayTotalMoney + $money > $v->per_day_amount) {
                    unset($seletedQr[$k]);
                    $notOk['qrTodayMoney_' . $v->qr_code] = $v;
                    array_push($close, $v->id);
                    Yii::info(json_encode($v->toArray(), 256), 'qrTodayMoney_' . $data['mch_order_id']);
                    continue;
                }

                //是否超过每日接单笔数上限
                $todayTotalTimes = Common::qrTodayTimes($v->qr_code, 0, 0, 1);
                if ($todayTotalTimes >= $v->per_day_orders) {
                    unset($seletedQr[$k]);
                    $notOk['todayTotalTimes_' . $v->qr_code] = $v;
                    array_push($close, $v->id);
                    Yii::info(json_encode(['data' => $v->toArray(), 'todayTotalTimes' => $todayTotalTimes . '-' . $v->per_day_orders], 256), 'qrTodayTimes_' . $data['mch_order_id']);
                    continue;
                }

                //支付宝好友红包过滤无心跳的支付宝账号， 过滤条件：当前码为支付宝的码、后台支付宝好友红包功能配置为开、当前码对应的支付宝账号无心跳
                if($v->qr_type == 1 && $alipayRedEnvelopSwitch == 1){

                    //红包功能依赖支付宝的uid, 如果码信息中未维护uid, 跳过此码 (红包心跳接口中会处理uid)    //uid 要求16位纯数字
                    if(!(isset($v->alipay_uid) && $v->alipay_uid && is_numeric($v->alipay_uid) && strlen($v->alipay_uid) == 16)){
                        unset($seletedQr[$k]);
                        $notOk['alipayUIDInvalid_' . $v->qr_code] = $v;
                        array_push($close, $v->id);
                        Yii::info(json_encode(['data' => $v->toArray()], 256), 'alipayUIDInvalid_' . $data['mch_order_id']);
                        continue;
                    }

                    //redis中查找当前码(支付宝账号)的心跳， 如果无心跳， 则排除此码
                    $pulse = \Yii::$app->redis->get('AlipayRedEnvelopPulse_'.$v->alipay_uid);
                    if(!$pulse){
                        unset($seletedQr[$k]);
                        $notOk['alipayNoPulse_' . $v->qr_code] = $v;
                        array_push($close, $v->id);
                        Yii::info(json_encode(['data' => $v->toArray()], 256), 'alipayRedEnvelopNoPulse_' . $data['mch_order_id']);
                        continue;
                    }
                }

            }

            //优先同城出码(市=》省=》通用)
            if ($data['user_ip'] && $order_type != 4) {
                $ipInfo = IpLocation::getLocation($data['user_ip']);
                if ($ipInfo['city']) {
                    $ipInfo['city'] = str_replace('市', '', $ipInfo['city']);
                }
                if ($ipInfo['province']) {
                    $ipInfo['province'] = str_replace('省', '', $ipInfo['province']);
                }
                Yii::info($ipInfo, 'OrderIpInfo_' . $data['mch_order_id']);
                $city_qr = [];
                $province_qr = [];
                foreach ($seletedQr as $kk => $vv) {
                    //查看后台有没重定向当前二维码的所在地，有则替换二维码本来的所在地
                    $resetLocation = Yii::$app->redis->get($vv->qr_code . '_redis');
                    $vv->qr_location = !empty($resetLocation) ? $resetLocation : $vv->qr_location;
                    if ($ipInfo['city'] && $vv->qr_location && strpos($vv->qr_location, $ipInfo['city'])) {
                        array_push($city_qr, $vv);
                        unset($seletedQr[$kk]);
                        continue;
                    } elseif ($ipInfo['province'] && $vv->qr_location && strpos($vv->qr_location, $ipInfo['province'])) {
                        array_push($province_qr, $vv);
                        unset($seletedQr[$kk]);
                        continue;
                    }
                }
                $seletedQr = array_merge($city_qr, $province_qr, $seletedQr);
            }
            Yii::info(count($seletedQr), 'selectQrCode_SameCity_' . $data['mch_order_id']);

            //筛选掉的二维码
            Yii::info(count($notOk), 'selectQrCodeBad_' . $data['mch_order_id']);
            //返回剩下的所有可用的二维码
            Yii::info(count($seletedQr), 'selectQrCodeOk_' . $data['mch_order_id']);

            QrCode::updateAll(['qr_status' => 1], ['in', 'id', $close]);

            if (count($seletedQr)) {
                return ['result' => 1, 'data' => $seletedQr, 'msg' => '二维码获取成功'];
            } else {
                return ['result' => 0, 'msg' => '筛选过后，无可用二维码'];
            }
        } catch (\Exception $e) {
            Yii::error(['data' => $data, 'msg' => $e->getMessage() . '-' . $e->getLine() . '-' . $e->getFile()], 'selectQrCode_Exception');
            return ['result' => 0, 'msg' => '获取二维码发生异常'];
        }
    }
}
