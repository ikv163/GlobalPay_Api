<?php

namespace app\models;

use app\common\Common;
use Yii;
use yii\validators\NumberValidator;

/**
 * This is the model class for table "cashier".
 *
 * @property int $id
 * @property string $username 用户名
 * @property string $login_password 登录密码
 * @property string $pay_password 资金密码
 * @property float|null $income 收益
 * @property float $security_money 保证金
 * @property float $wechat_rate 微信费率
 * @property float $alipay_rate 支付宝费率
 * @property float|null $wechat_amount 微信可收额度
 * @property float|null $alipay_amount 支付宝可收额度
 * @property string|null $parent_name 上级用户名
 * @property string|null $wechat 个人微信
 * @property string|null $alipay 个人支付宝
 * @property int|null $priority 优先出码（数字越小越优先）
 * @property string|null $telephone 手机号码
 * @property int $agent_class 等级
 * @property int|null $cashier_status 收款员状态 0禁用 1启用 2删除
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 修改时间
 * @property string|null $login_at 上次登录时间
 * @property string|null $remark 备注
 */
class Cashier extends \yii\db\ActiveRecord
{

    //定义收款员状态： 0禁用  1启用  2删除
    public static $CashierStatusOff = 0;
    public static $CashierStatusOn = 1;
    public static $CashierStatusDelete = 2;

    //定义资金密码格式
    public static $PayPassPreg = '/^\d{6}$/';

    //定义手机号码格式
    public static $PhonePreg = '/^1[3-9]\d{9}$/';


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'cashier';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['income', 'security_money', 'wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate', 'wechat_amount', 'alipay_amount', 'union_pay_amount', 'bank_card_amount'], 'number'],
            [['priority', 'agent_class', 'cashier_status'], 'integer'],
            [['insert_at', 'update_at', 'login_at'], 'safe'],
            [['username', 'parent_name', 'wechat', 'alipay'], 'string', 'max' => 50],
            [['login_password', 'pay_password', 'remark'], 'string', 'max' => 255],
            [['telephone'], 'string', 'max' => 20],
            [['username'], 'unique', 'when' => function ($model) {
                return $this->isNewRecord;
            }],
            [['username', 'login_password', 'invite_code'], 'required'],
            [['username', 'invite_code'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => Yii::t('app/model', 'usernameX'),
            'login_password' => 'Login Password',
            'pay_password' => 'Pay Password',
            'income' => 'Income',
            'security_money' => 'Security Money',
            'wechat_rate' => 'Wechat Rate',
            'alipay_rate' => 'Alipay Rate',
            'wechat_amount' => 'Wechat Amount',
            'alipay_amount' => 'Alipay Amount',
            'parent_name' => 'Parent Name',
            'wechat' => 'Wechat',
            'alipay' => 'Alipay',
            'priority' => 'Priority',
            'telephone' => 'Telephone',
            'agent_class' => 'Agent Class',
            'cashier_status' => 'Cashier Status',
            'insert_at' => 'Insert At',
            'update_at' => 'Update At',
            'login_at' => 'Login At',
            'remark' => 'Remark',
            'bank_card_rate' => 'bank_card_rate',
            'union_pay_rate' => 'union_pay_rate',
            'bank_card_amount' => 'bank_card_amount',
            'union_pay_amount' => 'union_pay_amount',
        ];
    }

    /**
     * @param $username
     * @return null
     * 获取一级代理
     */
    public static function getFirstClass($username)
    {
        $parent = Yii::$app->redis->get(md5($username) . 'Parent');
        if ($parent) {
            Yii::$app->redis->setex(md5($username) . 'Parent', 300, $parent);
            return $parent;
        }
        $cashier = Cashier::find()->where(['username' => $username])->select(['parent_name'])->one();
        if (empty($cashier->parent_name)) {
            Yii::$app->redis->setex(md5($username) . 'Parent', 300, $username);
            return $username;
        }
        $temp = $cashier->parent_name;
        while ($cashier->parent_name) {
            $cashier = Cashier::find()->where(['username' => $cashier->parent_name])->select(['parent_name'])->one();
            if (!($cashier->parent_name)) {
                Yii::$app->redis->setex(md5($username) . 'Parent', 300, $temp);
                return $temp;
            } else {
                $temp = $cashier->parent_name;
            }
        }
        Yii::$app->redis->setex(md5($username) . 'Parent', 300, $temp);
        return $temp;
    }


    /**
     * 生成唯一的邀请码
     * @return string
     */
    public static function generateCashierInviteCode()
    {
        $code = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $rand = $code[rand(0, 25)]
            . strtoupper(dechex(date('m')))
            . date('d') . substr(time(), -5)
            . substr(microtime(), 2, 5)
            . sprintf('%02d', rand(0, 99));
        for (
            $a = md5($rand, true),
            $s = '123456789ABCDEFGHIJKLMNOPQRSTUVabcdefghijkmnpqrstuvwxy',
            $d = '',
            $f = 0;
            $f < 4;
            $g = ord($a[$f]),
            $d .= $s[($g ^ ord($a[$f + 4])) - $g & 0x1F],
            $f++
        ) ;

        $data = Cashier::find()->where('invite_code=:code', array(':code' => $d))->asArray()->one();
        if ($data && isset($data['invite_code']) && $data['invite_code']) {
            return self::generateCashierInviteCode();
        }

        return $d;

    }

    /**
     * @param $username
     * @param array $team
     * @return array
     * 得到团队成员
     */
    public static function calcTeam($first, &$team = [])
    {
        //先找到当前收款员的直接下级
        $next = Cashier::find()->where(['parent_name' => $first['username']])->andWhere(['<', 'cashier_status', 2])->asArray()->all();

        foreach ($next as $k => $v) {
            array_push($team, $v);
            Cashier::calcTeam($v, $team);
        }
        return $team;
    }

    public static function teamIncome($username)
    {
        $data = Yii::$app->redis->get($username . 'All');
        if ($data) {
            return json_decode($data, 1);
        }

        //支付宝当日总接单总量
        $alipayAll = [];
        $alipayAll['OrderSearch']['username'] = $username;
        $alipayAll['OrderSearch']['query_team'] = 2;
        $alipayAll['OrderSearch']['order_type'] = 1;
        //支付宝当日总成功接单量
        $alipaySuccess = [];
        $alipaySuccess['OrderSearch']['username'] = $username;
        $alipaySuccess['OrderSearch']['query_team'] = 2;
        $alipaySuccess['OrderSearch']['order_type'] = 1;
        $alipaySuccess['OrderSearch']['order_status'] = 999;

        //微信当日总接单总量
        $wechatAll = [];
        $wechatAll['OrderSearch']['username'] = $username;
        $wechatAll['OrderSearch']['query_team'] = 2;
        $wechatAll['OrderSearch']['order_type'] = 2;
        //微信当日总成功接单量
        $wechatSuccess = [];
        $wechatSuccess['OrderSearch']['username'] = $username;
        $wechatSuccess['OrderSearch']['query_team'] = 2;
        $wechatSuccess['OrderSearch']['order_type'] = 2;
        $wechatSuccess['OrderSearch']['order_status'] = 999;

        //云闪付当日总接单总量
        $unionPayAll = [];
        $unionPayAll['OrderSearch']['username'] = $username;
        $unionPayAll['OrderSearch']['query_team'] = 2;
        $unionPayAll['OrderSearch']['order_type'] = 3;
        //云闪付当日总成功接单量
        $unionPaySuccess = [];
        $unionPaySuccess['OrderSearch']['username'] = $username;
        $unionPaySuccess['OrderSearch']['query_team'] = 2;
        $unionPaySuccess['OrderSearch']['order_type'] = 3;
        $unionPaySuccess['OrderSearch']['order_status'] = 999;

        //银行卡当日总接单总量
        $bankCardAll = [];
        $bankCardAll['OrderSearch']['username'] = $username;
        $bankCardAll['OrderSearch']['query_team'] = 2;
        $bankCardAll['OrderSearch']['order_type'] = 4;
        //银行卡当日总成功接单量
        $bankCardSuccess = [];
        $bankCardSuccess['OrderSearch']['username'] = $username;
        $bankCardSuccess['OrderSearch']['query_team'] = 2;
        $bankCardSuccess['OrderSearch']['order_type'] = 4;
        $bankCardSuccess['OrderSearch']['order_status'] = 999;

        $data['zfbTotalMoney'] = 0;
        $data['wxTotalMoney'] = 0;
        $data['unionPayTotalMoney'] = 0;
        $data['bankCardTotalMoney'] = 0;
        $data['totalMoney'] = 0;
        $data['zfbTotalTimes'] = 0;
        $data['wxTotalTimes'] = 0;
        $data['unionPayTotalTimes'] = 0;
        $data['bankCardTotalTimes'] = 0;
        $data['totalTimes'] = 0;

        $data['zfbTotalMoneyAll'] = 0;
        $data['wxTotalMoneyAll'] = 0;
        $data['unionPayTotalMoneyAll'] = 0;
        $data['bankCardTotalMoneyAll'] = 0;
        $data['totalMoneyAll'] = 0;
        $data['zfbTotalTimesAll'] = 0;
        $data['wxTotalTimesAll'] = 0;
        $data['unionPayTotalTimesAll'] = 0;
        $data['bankCardTotalTimesAll'] = 0;
        $data['totalTimesAll'] = 0;

        //支付宝-总
        $searchModel1 = new OrderSearch();
        $query1 = $searchModel1->teamIncome($alipayAll, 1);
        $data['zfbTotalMoneyAll'] = $query1->sum('order.order_amount');
        $data['zfbTotalTimesAll'] = $query1->count('order.id');
        $data['zfbTotalMoneyAll'] = $data['zfbTotalMoneyAll'] < 1 ? 0 : $data['zfbTotalMoneyAll'];
        $data['zfbTotalTimesAll'] = $data['zfbTotalTimesAll'] < 1 ? 0 : $data['zfbTotalTimesAll'];

        //支付宝-成功
        $searchModel2 = new OrderSearch();
        $query2 = $searchModel2->teamIncome($alipaySuccess, 1);
        $data['zfbTotalMoney'] = $query2->sum('order.actual_amount');
        $data['zfbTotalTimes'] = $query2->count('order.id');
        $data['zfbTotalMoney'] = $data['zfbTotalMoney'] < 1 ? 0 : $data['zfbTotalMoney'];
        $data['zfbTotalTimes'] = $data['zfbTotalTimes'] < 1 ? 0 : $data['zfbTotalTimes'];

        //微信-总
        $searchModel3 = new OrderSearch();
        $query3 = $searchModel3->teamIncome($wechatAll, 1);
        $data['wxTotalMoneyAll'] = $query3->sum('order.order_amount');
        $data['wxTotalTimesAll'] = $query3->count('order.id');
        $data['wxTotalMoneyAll'] = $data['wxTotalMoneyAll'] < 1 ? 0 : $data['wxTotalMoneyAll'];
        $data['wxTotalTimesAll'] = $data['wxTotalTimesAll'] < 1 ? 0 : $data['wxTotalTimesAll'];

        //微信-成功
        $searchModel4 = new OrderSearch();
        $query4 = $searchModel4->teamIncome($wechatSuccess, 1);
        $data['wxTotalMoney'] = $query4->sum('order.actual_amount');
        $data['wxTotalTimes'] = $query4->count('order.id');
        $data['wxTotalMoney'] = $data['wxTotalMoney'] < 1 ? 0 : $data['wxTotalMoney'];
        $data['wxTotalTimes'] = $data['wxTotalTimes'] < 1 ? 0 : $data['wxTotalTimes'];

        //云闪付-总
        $searchModel5 = new OrderSearch();
        $query5 = $searchModel5->teamIncome($unionPayAll, 1);
        $data['unionPayMoneyAll'] = $query5->sum('order.order_amount');
        $data['unionPayTotalTimesAll'] = $query5->count('order.id');
        $data['unionPayTotalMoneyAll'] = $data['unionPayTotalMoneyAll'] < 1 ? 0 : $data['unionPayTotalMoneyAll'];
        $data['unionPayTotalTimesAll'] = $data['unionPayTotalTimesAll'] < 1 ? 0 : $data['unionPayTotalTimesAll'];

        //云闪付-成功
        $searchModel6 = new OrderSearch();
        $query6 = $searchModel6->teamIncome($unionPaySuccess, 1);
        $data['unionPayTotalMoney'] = $query6->sum('order.actual_amount');
        $data['unionPayTotalTimes'] = $query6->count('order.id');
        $data['unionPayTotalMoney'] = $data['unionPayTotalMoney'] < 1 ? 0 : $data['unionPayTotalMoney'];
        $data['unionPayTotalTimes'] = $data['unionPayTotalTimes'] < 1 ? 0 : $data['unionPayTotalTimes'];

        //银行卡-总
        $searchModel7 = new OrderSearch();
        $query7 = $searchModel7->teamIncome($bankCardAll, 1);
        $data['bankCardMoneyAll'] = $query7->sum('order.order_amount');
        $data['bankCardTotalTimesAll'] = $query7->count('order.id');
        $data['bankCardTotalMoneyAll'] = $data['bankCardTotalMoneyAll'] < 1 ? 0 : $data['bankCardTotalMoneyAll'];
        $data['bankCardTotalTimesAll'] = $data['bankCardTotalTimesAll'] < 1 ? 0 : $data['bankCardTotalTimesAll'];

        //银行卡-成功
        $searchModel8 = new OrderSearch();
        $query8 = $searchModel8->teamIncome($bankCardSuccess, 1);
        $data['bankCardTotalMoney'] = $query8->sum('order.actual_amount');
        $data['bankCardTotalTimes'] = $query8->count('order.id');
        $data['bankCardTotalMoney'] = $data['bankCardTotalMoney'] < 1 ? 0 : $data['bankCardTotalMoney'];
        $data['bankCardTotalTimes'] = $data['bankCardTotalTimes'] < 1 ? 0 : $data['bankCardTotalTimes'];

        //总共
        $data['totalMoneyAll'] = bcadd($data['zfbTotalMoneyAll'], ($data['wxTotalMoneyAll'] + $data['unionPayTotalMoneyAll'] + $data['bankCardTotalMoneyAll']), 2);
        $data['totalTimesAll'] = bcadd($data['zfbTotalTimesAll'], ($data['wxTotalTimesAll'] + $data['unionPayTotalTimesAll'] + $data['bankCardTotalTimesAll']), 0);
        $data['totalMoney'] = bcadd($data['zfbTotalMoney'], ($data['wxTotalMoney'] + $data['unionPayTotalMoney'] + $data['bankCardTotalMoney']), 0);
        $data['totalTimes'] = bcadd($data['zfbTotalTimes'], ($data['wxTotalTimes'] + $data['unionPayTotalTimes'] + $data['bankCardTotalTimes']), 0);

        Yii::$app->redis->setex($username . 'All', 60, json_encode($data, 256));
        return $data;
    }

    /**
     * @param $team
     * @return |null
     * 统计团队当然总收款额度/次数、支付宝/微信总收款额度/次数
     */
    public static function teamIncome1($team)
    {
        $data['zfbTotalMoney'] = 0;
        $data['wxTotalMoney'] = 0;
        $data['totalMoney'] = 0;
        $data['zfbTotalTimes'] = 0;
        $data['wxTotalTimes'] = 0;
        $data['totalTimes'] = 0;

        $data['zfbTotalMoneyAll'] = 0;
        $data['wxTotalMoneyAll'] = 0;
        $data['totalMoneyAll'] = 0;
        $data['zfbTotalTimesAll'] = 0;
        $data['wxTotalTimesAll'] = 0;
        $data['totalTimesAll'] = 0;
        if (!$team) {
            return $data;
        }
        //总成功数
        $zfbTotalMoneySuccess = 0;
        $wxTotalMoneySuccess = 0;
        $zfbTotalTimesSuccess = 0;
        $wxTotalTimesSuccess = 0;
        //总数（成功+失败）
        $zfbTotalMoneyAll = 0;
        $wxTotalMoneyAll = 0;
        $zfbTotalTimesAll = 0;
        $wxTotalTimesAll = 0;
        foreach ($team as $k => $v) {
            $zfbTotalMoneySuccess += Common::cashierTodayMoney($v['username'], 1, 0, 0, 1);
            $wxTotalMoneySuccess += Common::cashierTodayMoney($v['username'], 2, 0, 0, 1);
            $zfbTotalTimesSuccess += Common::cashierTodayTimes($v['username'], 1, 0, 0, 1);
            $wxTotalTimesSuccess += Common::cashierTodayTimes($v['username'], 2, 0, 0, 1);

            $zfbTotalMoneyAll += Common::cashierTodayMoney($v['username'], 1);
            $wxTotalMoneyAll += Common::cashierTodayMoney($v['username'], 2);
            $zfbTotalTimesAll += Common::cashierTodayTimes($v['username'], 1);
            $wxTotalTimesAll += Common::cashierTodayTimes($v['username'], 2);
        }
        $data['zfbTotalMoney'] = $zfbTotalMoneySuccess;
        $data['wxTotalMoney'] = $wxTotalMoneySuccess;
        $data['totalMoney'] = $zfbTotalMoneySuccess + $wxTotalMoneySuccess;
        $data['zfbTotalTimes'] = $zfbTotalTimesSuccess;
        $data['wxTotalTimes'] = $wxTotalTimesSuccess;
        $data['totalTimes'] = $zfbTotalTimesSuccess + $wxTotalTimesSuccess;

        $data['zfbTotalMoneyAll'] = $zfbTotalMoneyAll;
        $data['wxTotalMoneyAll'] = $wxTotalMoneyAll;
        $data['totalMoneyAll'] = $zfbTotalMoneyAll + $wxTotalMoneyAll;
        $data['zfbTotalTimesAll'] = $zfbTotalTimesAll;
        $data['wxTotalTimesAll'] = $wxTotalTimesAll;
        $data['totalTimesAll'] = $zfbTotalTimesAll + $wxTotalTimesAll;
        return $data;
    }

    /**
     * 获取用户信息
     * @params   string   $username      用户名
     * @return   array
     */
    public static function getUserInfo($username)
    {
        $fields = "cashier.*, count(b.id) as member_count";
        $userData = Cashier::find()
            ->select($fields)
            ->leftJoin('cashier b', 'b.parent_name = :username')
            ->where('cashier.username=:username and (cashier.cashier_status=1 or cashier.cashier_status=0)', array(':username' => $username))
            ->asArray()
            ->one();

        $promoteUrl = $userData && isset($userData['invite_code']) && $userData['invite_code'] ? $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/site/reg?p=' . $userData['invite_code'] : '';
        if ($userData) {
            $userData['promote_url'] = $promoteUrl;

            //计算用户可提现金额， 返回
            $withdrawHandlingFee = Withdraw::calHandlingFee($userData['security_money']);
            $availableWithdrawAmount = $userData['security_money'] >= $withdrawHandlingFee ? round($userData['security_money'] - $withdrawHandlingFee, 2) : 0.00;
            $userData['available_withdraw_amount'] = $availableWithdrawAmount;

        }
        return $userData ? $userData : array();
    }

    /**
     * @param $data
     * @return bool|string
     * 添加下级
     */
    public static function addMember($data)
    {
        try {
            $cashier = new Cashier();
            $cashier->load($data, '');

            $parent = Cashier::getUserInfo($cashier->parent_name);
            if ($parent['wechat_rate'] < $cashier->wechat_rate) {
                return '下级不能大于上级【微信费率】';
            }
            if ($parent['alipay_rate'] < $cashier->alipay_rate) {
                return '下级不能大于上级【支付宝费率】';
            }
            if ($parent['union_pay_rate'] < $cashier->union_pay_rate) {
                return '下级不能大于上级【云闪付费率】';
            }
            if ($parent['bank_card_rate'] < $cashier->bank_card_rate) {
                return '下级不能大于上级【银行卡费率】';
            }
            $cashier->agent_class = intval($parent['agent_class'] + 1);
            $cashier->invite_code = Cashier::generateCashierInviteCode();
            $cashier->login_password = md5($cashier->login_password);
            if (!$cashier->validate()) {
                return Common::getModelError($cashier);
            }
            if ($cashier->save()) {
                return true;
            } else {
                return '添加下级失败';
            }
        } catch (\Exception $e) {
            Yii::error(json_encode(['data' => $cashier->toArray(), 'msg' => $e->getMessage()], 256), 'Cashier/addMember_error');
            return '添加下级异常';
        }
    }

    /**
     * @param $data
     * @return bool|string
     * 修改下级
     */
    public static function editMember($data, $id, $username)
    {
        try {
            $cashier = Cashier::find()->where(['id' => $id])->one();
            if (!$cashier) {
                return '未查询到此下级信息';
            } elseif ($cashier->parent_name != $username) {
                return '你无权修改此下级信息';
            }

            $parent = Cashier::getUserInfo($cashier->parent_name);

            if ($data['login_password'] != null) {
                $cashier->login_password = md5($data['login_password']);
            }
            $cashier->wechat_rate = $data['wechat_rate'];
            $cashier->alipay_rate = $data['alipay_rate'];
            $cashier->union_pay_rate = $data['union_pay_rate'];
            $cashier->bank_card_rate = $data['bank_card_rate'];
            $cashier->parent_name = $data['parent_name'];
            $cashier->cashier_status = $data['cashier_status'];
            $cashier->wechat = $data['wechat'];
            $cashier->alipay = $data['alipay'];
            $cashier->telephone = $data['telephone'];
            $cashier->remark = $data['remark'];

            if ($parent['wechat_rate'] < $cashier->wechat_rate) {
                return '【微信费率】下级不能大于上级';
            }
            if ($parent['alipay_rate'] < $cashier->alipay_rate) {
                return '【支付宝费率】下级不能大于上级';
            }
            if ($parent['union_pay_rate'] < $cashier->union_pay_rate) {
                return '【云闪付费率】下级不能大于上级';
            }
            if ($parent['bank_card_rate'] < $cashier->bank_card_rate) {
                return '【银行卡费率】下级不能大于上级';
            }

            $next = Cashier::find()->where(['parent_name' => $cashier->username])->select(['wechat_rate'])->orderBy(['wechat_rate' => SORT_DESC])->one();
            if ($next) {
                if ($next->wechat_rate > $cashier->wechat_rate) {
                    return $cashier->username . '的微信费率不能低于他下级的微信费率:' . $next->wechat_rate;
                }
            }

            $next = Cashier::find()->where(['parent_name' => $cashier->username])->select(['alipay_rate'])->orderBy(['alipay_rate' => SORT_DESC])->one();
            if ($next) {
                if ($next->alipay_rate > $cashier->alipay_rate) {
                    return $cashier->username . '的支付宝费率不能低于他下级的支付宝费率:' . $next->alipay_rate;
                }
            }

            $next = Cashier::find()->where(['parent_name' => $cashier->username])->select(['union_pay_rate'])->orderBy(['union_pay_rate' => SORT_DESC])->one();
            if ($next) {
                if ($next->union_pay_rate > $cashier->union_pay_rate) {
                    return $cashier->username . '的云闪付费率不能低于他下级的云闪付费率:' . $next->union_pay_rate;
                }
            }

            $next = Cashier::find()->where(['parent_name' => $cashier->username])->select(['bank_card_rate'])->orderBy(['bank_card_rate' => SORT_DESC])->one();
            if ($next) {
                if ($next->bank_card_rate > $cashier->bank_card_rate) {
                    return $cashier->username . '的银行卡费率不能低于他下级的银行卡费率:' . $next->bank_card_rate;
                }
            }


            if (!$cashier->validate()) {
                return Common::getModelError($cashier);
            }

            if ($cashier->save()) {
                //若是禁用、删除下级， 则将下级踢下线
                if (in_array($data['cashier_status'], array(0, 2))) {
                    $token = \Yii::$app->redis->get($cashier->username);
                    if ($token && \Yii::$app->redis->get('User_' . $token)) {
                        \Yii::$app->redis->del('User_' . $token);
                    }
                }
                return true;
            } else {
                return '修改下级失败';
            }
        } catch (\Exception $e) {
            Yii::error(json_encode(['data' => $cashier->toArray(), 'msg' => $e->getMessage()], 256), 'Cashier_editMember_error');
            return '修改下级异常';
        }
    }


    /**
     * 更新收款员各额度
     * @param $username          string      收款员用户名
     * @param $amount            number      变动金额
     * @param $updateField       string      额度字段
     * @return bool
     * @throws \yii\db\Exception
     */
    public static function updateCashierBalance($username, $amount, $updateField)
    {

        \Yii::info("{$username}--{$amount}--{$updateField}", 'cashierModel/updatecashierbalance');

        //验证额度字段
        if (!in_array($updateField, array('security_money', 'income', 'wechat_amount', 'alipay_amount'))) {
            return false;
        }

        //变动金额为负时，需要验证对应的额度是否够减
        if ($amount < 0) {
            $cashier = Cashier::find()->select('security_money, income,  wechat_amount, alipay_amount')->where('username=:username', array(':username' => $username))->asArray()->one();

            \Yii::info("{$username}--{$amount}--{$updateField}--" . json_encode($cashier, JSON_UNESCAPED_UNICODE), 'updateCashierBalance');

            if (!($cashier && isset($cashier[$updateField]) && is_numeric($cashier[$updateField]) && $cashier[$updateField] >= 0)) {
                return false;
            }

            if (abs($amount) > $cashier[$updateField]) {
                return false;
            }

        }

        //执行更改
        $res = \Yii::$app->db->createCommand(
            "update `cashier` set {$updateField}={$updateField}+:money, `update_at`=:time where `username`=:username",
            array(
                ':money' => $amount,
                ':time' => date('Y-m-d H:i:s'),
                ':username' => $username
            )
        )->execute();

        return (boolean)$res;
    }


    /**
     * 获取下级
     * @param $username
     * @return array
     */
    public static function getFollowers($username)
    {
        return Cashier::find()->where('parent_name=:username', array(':username' => $username))->asArray()->all();
    }


    /**
     *
     */
    public static function generateCashierAuthToken($cashier)
    {
        return md5($cashier['username'] . \Yii::$app->params['Cashier_Auth_Token_Key'] . $cashier['id']);
    }

}
