<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "finance_detail".
 *
 * @property int $id
 * @property float $change_amount 变动金额
 * @property float $before_amount 变动前的金额
 * @property float $after_amount 变动后的金额
 * @property int $user_type 用户类型 1平台 2商户 3收款员
 * @property string $username 用户名
 * @property int $finance_type 资金类型
 * 1保证金
 * 2收益
 * 3微信可收额度
 * 4支付宝可收额度
 * @property string $insert_at 添加时间
 * @property string|null $remark 备注
 */
class FinanceDetail extends \yii\db\ActiveRecord
{
    //定义用户类型：1平台  2商户  3收款员
    public static $UserTypePlatform = 1;
    public static $UserTypeMerchant = 2;
    public static $UserTypeCashier = 3;
    public static $UserTypeRel = array(
        '1' => '平台',
        '2' => '商户',
        '3' => '收款员',
    );

    //定义资金类型:
    // 1保证金  2收益  3微信可收额度   4支付宝可收额度  5手续费 6收款员提现  7收款员提现返还
    // 8收款员微信接单   9收款员微信接单返还    10商户提现   11商户提现返还    12收款员支付宝接单   13收款员支付宝接单返还
    // 14取款手续费返还
    public static $FinanceTypeMargin = 1;
    public static $FinanceTypeIncome = 2;
    public static $FinanceTypeWechatReceivable = 3;
    public static $FinanceTypeAlipayReceivable = 4;
    public static $FinanceTypeHandlingFee = 5;
    public static $FinanceTypeWithdraw = 6;
    public static $FinanceTypeWithdrawReturn = 7;
    public static $FinanceTypeCashierWechatOrder = 8;
    public static $FinanceTypeCashierWechatOrderRefund = 9;
    public static $FinanceTypeMerchantWithdraw = 10;
    public static $FinanceTypeMerchantWithdrawRefund = 11;
    public static $FinanceTypeCashierAlipayOrder = 12;
    public static $FinanceTypeCashierAlipayOrderRefund = 13;
    public static $FinanceTypeHandlingFeeReturn = 14;
    public static $FinanceTypeUnionPayReceivable = 16;

    public static $FinanceTypeRel = array(
        '1' => '保证金',
        '2' => '收益',
        '3' => '微信可收额度',
        '4' => '支付宝可收额度',
        '5' => '手续费',
        '6' => '提现',
        '7' => '提现返还',
        '8' => '微信接单',
        '9' => '微信接单返还',
        '10' => '提单',
        '11' => '提单返还',
        '12' => '支付宝接单',
        '13' => '支付宝接单返还',
        '14' => '云闪付接单',
        '15' => '云闪付接单返还',
        '16' => '云闪付可收额度',
        '17' => '银行卡接单',
        '18' => '银行卡接单返还',
        '19' => '银行卡可收额度',
    );

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'finance_detail';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['change_amount', 'before_amount', 'after_amount'], 'number'],
            [['user_type', 'finance_type'], 'required'],
            [['user_type', 'finance_type'], 'integer'],
            [['insert_at'], 'safe'],
            [['username'], 'string', 'max' => 50],
            [['remark'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'change_amount' => Yii::t('app', 'Change Amount'),
            'before_amount' => Yii::t('app', 'Before Amount'),
            'after_amount' => Yii::t('app', 'After Amount'),
            'user_type' => Yii::t('app', 'User Type'),
            'username' => Yii::t('app', 'Username'),
            'finance_type' => Yii::t('app', 'Finance Type'),
            'insert_at' => Yii::t('app', 'Insert At'),
            'remark' => Yii::t('app', 'Remark'),
        ];
    }

    /**
     * @param $username
     * @param $financeType
     * @param $changeMoney
     * @param $userType
     * @param string $remark
     * @return bool
     * 资金交易明细
     */
    public static function financeCalc($username, $financeType, $changeMoney, $userType, $remark = '')
    {
        //暂时不记录平台的明细
        if ($userType == 1) {
            return true;
        }

        $financeDetail = new FinanceDetail();
        $financeDetail->change_amount = $changeMoney;
        $financeDetail->user_type = $userType;
        $financeDetail->username = $username;
        $financeDetail->finance_type = $financeType;
        $financeDetail->remark = $remark;

        Yii::info($financeDetail->toArray(), 'FinanceDetail_financeCalc');

        $user = null;
        if ($userType == 2) {
            $user = Merchant::find()->select(['balance', 'used_money'])->where(['mch_name' => $username])->one();
        } elseif ($userType == 3) {
            $user = Cashier::find()->select(['income', 'security_money', 'wechat_amount', 'alipay_amount', 'union_pay_amount', 'bank_card_amount'])->where(['username' => $username])->one();
        }

        if ($user == null) {
            \Yii::info('user is null', 'FinanceDetail_financeCalc_user');
            return true;
        }
        \Yii::info($user->toArray(), 'FinanceDetail_financeCalc_user');
        //1保证金 2收益 3微信可收额度 4支付宝可收额度 5手续费 6提现 7提现返还 8微信接单 9微信接单返还 10提单 11提单返还 12支付宝接单 13支付宝接单返还 14云闪付接单 15云闪付接单返还 16云闪付可收额度 17银行卡接单 18银行卡接单返还 19银行卡可收额度
        $type = [
            1 => ['user3' => 'security_money', 'user2' => null],
            2 => ['user3' => 'income', 'user2' => 'balance'],
            3 => ['user3' => 'wechat_amount', 'user2' => null],
            4 => ['user3' => 'alipay_amount', 'user2' => null],
            5 => ['user3' => 'security_money', 'user2' => 'balance'],
            6 => ['user3' => 'security_money', 'user2' => 'balance'],
            7 => ['user3' => 'security_money', 'user2' => 'balance'],
            8 => ['user3' => 'wechat_amount', 'user2' => null],
            9 => ['user3' => 'wechat_amount', 'user2' => null],
            10 => ['user3' => null, 'user2' => 'used_money'],
            11 => ['user3' => null, 'user2' => 'used_money'],
            12 => ['user3' => 'alipay_amount', 'user2' => null],
            13 => ['user3' => 'alipay_amount', 'user2' => null],
            14 => ['user3' => 'union_pay_amount', 'user2' => null],
            15 => ['user3' => 'union_pay_amount', 'user2' => null],
            16 => ['user3' => 'union_pay_amount', 'user2' => null],
            17 => ['user3' => 'bank_card_amount', 'user2' => null],
            18 => ['user3' => 'bank_card_amount', 'user2' => null],
            19 => ['user3' => 'bank_card_amount', 'user2' => null],
        ];

        $amountField = isset($type[$financeType]) && isset($type[$financeType]['user' . $userType]) ? $type[$financeType]['user' . $userType] : '';

        if (!($amountField && isset($user[$amountField]))) {
            \Yii::warning(['user' => $user->toArray(), 'amountField' => $amountField], 'FinanceDetail/financeCalc');
            return true;
        }

        $financeDetail->before_amount = $user[$amountField];
        $financeDetail->after_amount = bcadd($user[$amountField], $changeMoney, 2);

        return $financeDetail->save();
    }
}
