<?php

namespace app\models;

use Yii;
use app\common\Common;

/**
 * This is the model class for table "withdraw".
 *
 * @property int $id
 * @property string $system_withdraw_id 系统订单ID
 * @property string $out_withdraw_id 外部订单ID
 * @property string $username 提款人用户名
 * @property int $user_type 用户类型 1商户 2收款员
 * @property float $withdraw_money 提款金额
 * @property int $bankcard_id 银行卡ID（user_bankcard）
 * @property int $withdraw_status 提现状态 0创建 1处理中 2成功 3失败 4驳回
 * @property string|null $withdraw_remark 用户提款备注
 * @property string|null $system_remark 系统备注
 * @property string|null $insert_at 提现时间
 * @property string|null $update_at 修改时间
 */
class Withdraw extends \yii\db\ActiveRecord
{

    public $pay_password;


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
        '0' => array(1,2,3,4),
        '1' => array(2,3,4),
        '2' => array(),
        '3' => array(),
        '4' => array(),
    );

    //提款用户类型
    public static $UserTypeMerchant = 1;
    public static $UserTypeCashier = 2;

    public static $UserTypeRel = array(
        '1' => '商户',
        '2' => '收款员',
    );


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'withdraw';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['system_withdraw_id','username', 'user_type','bankcard_id', 'withdraw_money', 'pay_password'], 'required'],
            [['system_withdraw_id'], 'unique'],
            [['user_type', 'bankcard_id', 'withdraw_status'], 'integer'],
            [['withdraw_money'], 'number', 'min'=>json_decode(SystemConfig::getSystemConfig('WithdrawConfigs'),true)['min_amount']],
            [['insert_at', 'update_at', 'pay_password'], 'safe'],
            [['system_withdraw_id', 'out_withdraw_id'], 'string', 'max' => 100],
            [['username'], 'string', 'max' => 50],
            [['withdraw_remark', 'system_remark'], 'string', 'max' => 255],
            [['handling_fee'], 'number', 'min'=>0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'system_withdraw_id' => Yii::t('app', 'System Withdraw ID'),
            'out_withdraw_id' => Yii::t('app', 'Out Withdraw ID'),
            'username' => Yii::t('app', 'Username'),
            'user_type' => Yii::t('app', 'User Type'),
            'withdraw_money' => Yii::t('app', 'Withdraw Money'),
            'bankcard_id' => Yii::t('app', 'Bankcard ID'),
            'withdraw_status' => Yii::t('app', 'Withdraw Status'),
            'withdraw_remark' => Yii::t('app', 'Withdraw Remark'),
            'system_remark' => Yii::t('app', 'System Remark'),
            'insert_at' => Yii::t('app', 'Insert At'),
            'update_at' => Yii::t('app', 'Update At'),
        ];
    }


    /**
     * 生成系统唯一提款订单号
     * @return string
     */
    public static function generateSystemWithdrawOrderNumber(){
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

        return 'W'.strtoupper(md5(microtime(true)));
    }


    /**
     * 根据当前订单状态，获取可以修改的状态
     * @param int $status
     * @return array
     */
    public static function getAvailableChangeStatus($status){
        $availableStatus = array();
        if(is_numeric($status) && in_array($status, array_keys(self::$OrderStatusRel)) && isset(self::$AvailableStatus[$status])){
            $availableStatus = self::$AvailableStatus[$status];
        }

        return $availableStatus;
    }


    /**
     * 获取收款员日取款统计数据
     */
    public static function getCashierDailyWithdrawStat($username, $date=''){

        $dateKey = $date ? date('Ymd',strtotime($date)) : date('Ymd');
        $cacheKey = 'CashierDailyWithdrawStat_'.$dateKey.'_'.$username;
        unset($dateKey);

        //初始化日统计数据格式
        $statData = array(
            'totalTimes' => 0,
            'totalAmount' => 0.00,
            'succeedTimes' => 0,
            'succeedAmount' => 0.00,
            'date' => $date,
        );

        try{
            //无缓存数据， 查db
            if($cacheData = \Yii::$app->redis->get($cacheKey)){
                return json_decode($cacheData, true);
            }else{
                $datas = Withdraw::find()
                    ->select('username, withdraw_money, handling_fee, withdraw_status, insert_at')
                    ->where(
                        'username=:username and user_type=:user_type and insert_at >= :start_time and insert_at <= :end_time',
                        array(':username'=>$username, ':user_type'=>self::$UserTypeCashier, ':start_time'=>date('Y-m-d 00:00:00', strtotime($date)), ':end_time'=>date('Y-m-d 23:59:59', strtotime($date)))
                    )
                    ->asArray()->All();

                if($datas){
                    $statData['totalTimes'] = count($datas);
                    foreach($datas as $data){
                        $statData['totalAmount'] += $data['withdraw_money'];
                        if($data['withdraw_status'] == self::$OrderStatusSucceed){
                            $statData['succeedTimes'] += 1;
                            $statData['succeedAmount'] += $data['withdraw_money'];
                        }
                    }
                    \Yii::$app->redis->setex($cacheKey, strtotime(date('Y-m-d 23:59:59'))-time(), json_encode($statData));

                    return $statData;
                }
            }
        }catch(\Exception $e){
            \Yii::error($e->getMessage(), 'withdrawModel/getCashierDailyWithdrawStat-error');
        }

        return $statData;
    }


    /**
     * 计算取款手续费
     * @params  number  $withdrawAmount     取款金额
     */
    public static function calHandlingFee($withdrawAmount){

        try{
            //取手续费配置数据
            $configData = json_decode(SystemConfig::getSystemConfig('Withdraw_Handling_Fee'),true);

            if(!(is_array($configData) && $configData)){
                return 0.00;
            }

            foreach($configData as $data){
                //取款金额在配置的金额范围内，且当前配置开启，则用此配置来计算金额
                if($data['s_amount'] <= $withdrawAmount && ($withdrawAmount <= $data['e_amount'] || $data['e_amount'] == 0) && $data['status'] == 1){
                    switch($data['type']){
                        case 1:
                            //按每笔固定金额
                            $fee = $data['value'];
                            break;

                        case 2:
                            //按取款金额的百分比
                            $fee = $withdrawAmount * ($data['value'] / 100);
                            break;

                        default:
                            $fee = 0.00;
                    }

                    return round($fee, 2);
                }

            }
        }catch(\Exception $e){
            \Yii::error($e->getMessage(), 'withdrawModel/calHandlingFee-error');
        }

        return 0.00;
    }


    /**
     * 提交取款到第三方
     */
    public static function sendWithdrawOrder(array $order){
        $apiDomain = SystemConfig::getSystemConfig('ApiDomain');
        $withdrawUrl = \Yii::$app->params['typay_deposit_url'].'/withdraw/create';

        $orderData = array(
            'merchant_id' => \Yii::$app->params['merchant_id'],
            'merchant_order_id'=>$order['merchant_order_id'],
            'user_level'=>$order['user_level'],
            'pay_type'=>$order['pay_type'],  //1银行卡转账，  888备付金转账
            'pay_amt' => $order['pay_amt'],
            'notify_url' => $apiDomain.'/api/withdraw-notify',
            'return_url' => '',
            'bank_code' => $order['bank_code'],
            'bank_num' => $order['bank_num'],
            'bank_owner' => $order['bank_owner'],
            'bank_address' => $order['bank_address'],
            'user_id'=>$order['user_id'],
            'user_ip' => $order['user_ip'],
            'remark' => $order['remark'],
        );

        $key = \Yii::$app->params['merchant_key'];

        $sign = md5('merchant_id=' . $orderData['merchant_id'] . '&merchant_order_id=' . $orderData['merchant_order_id'] . '&pay_type=' . $orderData['pay_type'] . '&pay_amt=' . $orderData['pay_amt'] . '&notify_url=' . $orderData['notify_url'] . '&return_url=' . $orderData['return_url'] . '&bank_code=' . $orderData['bank_code'] .'&bank_num='.$orderData['bank_num'].'&bank_owner='.$orderData['bank_owner'].'&bank_address='.$orderData['bank_address']. '&remark=' . $orderData['remark'] . '&key=' . $key);

        $orderData['sign'] = $sign;


        \Yii::info(json_encode($orderData, 256), 'withdraw_third_order_params');

        $res = Common::sendRequest($withdrawUrl, $orderData);

        \Yii::info($res, 'withdraw_third_order_res_origin_data');

        return json_decode($res,true);
    }

}
