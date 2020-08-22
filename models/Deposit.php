<?php

namespace app\models;

use app\common\Common;
use Yii;

/**
 * This is the model class for table "deposit".
 *
 * @property int $id
 * @property string $system_deposit_id 系统订单ID
 * @property string $out_deposit_id 外部订单ID
 * @property string $username 提款人用户名
 * @property float $deposit_money 充值金额
 * @property int $deposit_status 充值状态 0创建 1处理中 2成功 3失败 4驳回
 * @property string|null $deposit_remark 用户重置备注
 * @property string|null $system_remark 系统备注
 * @property string|null $insert_at 充值时间
 * @property string|null $update_at 修改时间
 */
class Deposit extends \yii\db\ActiveRecord
{
    public $bank_code;

    //定义订单状态 : 0创建  1处理中  2成功  3失败  4驳回
    public static $OrderStatusInit = 0;
    public static $OrderStatusProcessing = 1;
    public static $OrderStatusSucceed = 2;
    public static $OrderStatusFailed = 3;
    public static $OrderStatusRefused = 4;

    public static $OrderStatusRel = array(
        '0' => '创建',
        '1' => '处理中',
        '2' => '成功',
        '3' => '失败',
        '4' => '驳回',
    );

    //定义每个状态对应的可以修改的状态
    public static $AvailableStatus = array(
        '0' => array(1, 2, 3, 4),
        '1' => array(2, 3, 4),
        '2' => array(),
        '3' => array(),
        '4' => array(),
    );

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'deposit';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['system_deposit_id', 'username', 'deposit_money'], 'required'],
            [['system_deposit_id'], 'unique'],
            [['deposit_status', 'handle_type', 'system_bankcard_id'], 'integer', 'min' => 0],
            [['insert_at', 'update_at'], 'safe'],
            [['system_deposit_id', 'out_deposit_id'], 'string', 'max' => 100],
            [['username'], 'string', 'max' => 50],
            [['deposit_remark', 'system_remark'], 'string', 'max' => 255],

            [['pay_amount'], 'number', 'min' => '1.00'],
            [['pay_type', 'client_type', 'bank_code', 'pay_name'], 'required'],
            [['pay_type', 'client_type'], 'number', 'min' => 1],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'system_deposit_id' => Yii::t('app', 'System Deposit ID'),
            'out_deposit_id' => Yii::t('app', 'Out Deposit ID'),
            'username' => Yii::t('app', 'Username'),
            'deposit_money' => Yii::t('app', 'Deposit Money'),
            'deposit_status' => Yii::t('app', 'Deposit Status'),
            'deposit_remark' => Yii::t('app', 'Deposit Remark'),
            'system_remark' => Yii::t('app', 'System Remark'),
            'insert_at' => Yii::t('app', 'Insert At'),
            'update_at' => Yii::t('app', 'Update At'),
            'handle_type' => Yii::t('app', 'Handle Type'),
            'system_bankcard_id' => Yii::t('app', 'System Bankcard ID'),

            // [['pay_type','client_type', 'bank_code', 'pay_name'], 'required'],
            'pay_type' => \Yii::t('app/model', 'pay_type'),
            'client_type' => \Yii::t('app/model', 'client_type'),
            'bank_code' => \Yii::t('app/model', 'bank_code'),
            'pay_name' => \Yii::t('app/model', 'pay_name'),
        ];
    }


    /**
     * 生成系统唯一充值订单号
     * @return string
     */
    public static function generateSystemDepositOrderNumber()
    {
        /*if (function_exists('com_create_guid')) {
            $str = com_create_guid();
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $uuid = substr($charid, 0, 8)
                . substr($charid, 8, 4)
                . substr($charid, 12, 4)
                . substr($charid, 16, 4)
                . substr($charid, 20, 12);

            $str = $uuid;
        }*/

        return 'D' . strtoupper(md5(microtime(true)));
    }


    /**
     * 根据当前订单状态，获取可以修改的状态
     * @param int $status
     * @return array
     */
    public static function getAvailableChangeStatus($status)
    {
        $availableStatus = array();
        if (is_numeric($status) && in_array($status, array_keys(self::$OrderStatusRel)) && isset(self::$AvailableStatus[$status])) {
            $availableStatus = self::$AvailableStatus[$status];
        }

        return $availableStatus;
    }


    /**
     *
     */
    public static function getDepositBankcard($order, $username = null)
    {

        //获取后台配置的存款订单手动处理开关
        $switch = SystemConfig::getSystemConfig('Manual_Deposit_Switch');

        $returnData = array();

        if ($switch) {
            $firstParent = Cashier::getFirstClass($username);
            $card = Yii::$app->redis->get('bindDeposit' . $firstParent);
            $systemBankCard = '';
            if ($card) {
                $systemBankCard = SysBankcard::find()->where(['bankcard_number' => $card])->andWhere(['in', 'card_status', [0, 1]])->one();
            } else {
                return $returnData;
            }
//            $systemBankCard = SysBankcard::find()->where('card_status in (0, 1)')->orderBy('last_money_time ASC, last_order_time ASC')->asArray()->one();

            if ($systemBankCard) {
                $returnData['bankcard_number'] = $systemBankCard['bankcard_number'];
                $returnData['owner'] = $systemBankCard['bankcard_owner'];
                $returnData['bankname'] = isset(\Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']]) ? \Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']]['BankTypeName'] : '';
                $returnData['bank_address'] = $systemBankCard['bankcard_address'];
                $returnData['handle_type'] = 2;
                $returnData['system_bankcard_id'] = $systemBankCard['id'];
                $returnData['out_deposit_id'] = '';
                $returnData['bank_code'] = $systemBankCard['bank_code'];
            } else {
                //$bankcard = json_decode(self::sendDepositOrder($order->toArray()), true);

                //@todo 组合typay返回的银行卡数据
                /*$returnData['bankcard_number'] = $systemBankCard['bankcard_number'];
                $returnData['owner'] = $systemBankCard['bankcard_owner'];
                $returnData['bankname'] = isset(\Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']]) ? \Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']] : '';
                $returnData['bank_address'] = $systemBankCard['bankcard_address'];*/

                $returnData['bankcard_number'] = $returnData['owner'] = $returnData['bankname'] = $returnData['bank_address'] = $returnData['out_deposit_id'] = '';
                $returnData['handle_type'] = 1;
                $returnData['system_bankcard_id'] = 0;
            }

        } else {
            //$bankcard = json_decode(self::sendDepositOrder($order->toArray()), true);

            //@todo 组合typay返回的银行卡数据
            /*$returnData['bankcard_number'] = $systemBankCard['bankcard_number'];
            $returnData['owner'] = $systemBankCard['bankcard_owner'];
            $returnData['bankname'] = isset(\Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']]) ? \Yii::t('app', 'BankTypes')[$systemBankCard['bank_code']] : '';
            $returnData['bank_address'] = $systemBankCard['bankcard_address'];*/

            $returnData['bankcard_number'] = $returnData['owner'] = $returnData['bankname'] = $returnData['bank_address'] = $returnData['out_deposit_id'] = '';
            $returnData['handle_type'] = 1;
            $returnData['system_bankcard_id'] = 0;
        }

        return $returnData;
        //
    }


    /**
     * 提交充值订单到三方
     * @param array $order
     * @return bool|mixed
     */
    public static function sendDepositOrder(array $order)
    {

        $apiDomain = SystemConfig::getSystemConfig('ApiDomain');
        $depositUrl = \Yii::$app->params['typay_deposit_url'] . '/deposit/create';

        $orderData = array(
            'merchant_id' => \Yii::$app->params['merchant_id'],
            'merchant_order_id' => $order['system_deposit_id'],
            'user_level' => 0,
            'pay_type' => $order['pay_type'],
            'client_type' => $order['client_type'],
            'pay_amt' => sprintf('%.2f', $order['deposit_money']),
            'notify_url' => $apiDomain . \Yii::$app->params['deposit_notify_url'],
            'return_url' => '',
            'bank_code' => $order['bank_code'],
            'remark' => $order['deposit_remark'],
            'user_id' => $order['user_id'],
            'user_ip' => $order['user_ip'],
            'name' => $order['pay_name'],
            'member_account' => $order['username'],
        );

        $key = \Yii::$app->params['merchant_key'];

        $sign = md5('merchant_id=' . $orderData['merchant_id'] . '&merchant_order_id=' . $orderData['merchant_order_id'] . '&pay_type=' . $orderData['pay_type'] . '&pay_amt=' . $orderData['pay_amt'] . '&notify_url=' . $orderData['notify_url'] . '&return_url=' . $orderData['return_url'] . '&bank_code=' . $orderData['bank_code'] . '&remark=' . $order['deposit_remark'] . '&key=' . $key);

        $orderData['sign'] = $sign;


        \Yii::info(json_encode($orderData, 256), 'deposit_third_order_params');

        $res = Common::sendRequest($depositUrl, $orderData);

        \Yii::info($res, 'deposit_third_order_res_origin_data');

        return json_decode($res, true);

    }


    public static function generateSystemRemark()
    {
        $chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'm', 'n', 'p', 'q', 'r', 's',
            't', 'u', 'v', 'w', 'x', 'y', 'A', 'B', 'C', 'D',
            'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '@', '#', '&', '*');

        $length = count($chars);

        $remark = '';
        for ($i = 0; $i < 8; $i++) {
            $key = mt_rand(0, $length - 1);
            $remark .= $chars[$key];
        }
        $data = self::find()->select('system_remark')->where('system_remark=:remark', array(':remark' => $remark))->asArray()->one();
        if ($data && isset($data['system_remark']) && $data['system_remark']) {
            return self::generateSystemRemark();
        }

        return $remark;
    }

}
