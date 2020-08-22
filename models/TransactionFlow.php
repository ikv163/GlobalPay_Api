<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "transaction_flow".
 *
 * @property int $id
 * @property int $client_id 客户端ID
 * @property string $client_code 客户端简称
 * @property int $trade_type 流水来源 1支付宝 2微信
 * @property string $trans_id 交易流水单号
 * @property string $trans_account 流水所属账户
 * @property string $trans_time 流水交易时间
 * @property int $trans_type 流水类型 1收入 0支付
 * @property string $trans_amount 流水金额
 * @property int $trans_status 流水状态  0创建 1处理中 2成功 3超时异常
 * @property string $trans_fee 流水手续费
 * @property string $before_balance 流水之前的账户余额
 * @property string $trans_balance 当前账户余额
 * @property string $trans_remark 流水备注
 * @property string $trans_username 流水交易姓名
 * @property string $read_remark 被读取标识
 * @property string $md5_sign 流水唯一标识
 * @property string $pick_at 抓取时间
 * @property string $insert_at 添加时间
 * @property string $update_at 修改时间
 */
class TransactionFlow extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'transaction_flow';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['client_id', 'trade_type', 'trans_type', 'trans_status', 'trade_cate'], 'integer'],
            [['trade_type', 'trans_id'], 'required'],
            [['trans_time', 'pick_at', 'insert_at', 'update_at'], 'safe'],
            [['trans_amount', 'trans_fee', 'before_balance', 'trans_balance'], 'number'],
            [['client_code', 'trans_account', 'trans_username'], 'string', 'max' => 100],
            [['trans_id', 'trans_remark', 'read_remark', 'md5_sign'], 'string', 'max' => 255],
            [['md5_sign'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'id',
            'client_id' => 'client_id',
            'client_code' => 'client_code',
            'trade_type' => 'trade_type',
            'trans_id' => 'trans_id',
            'trans_account' => 'trans_account',
            'trans_time' => 'trans_time',
            'trans_type' => 'trans_type',
            'trans_amount' => 'trans_amount',
            'trans_status' => 'trans_status',
            'trans_fee' => 'trans_fee',
            'before_balance' => 'before_balance',
            'trans_balance' => 'trans_balance',
            'trans_remark' => 'trans_remark',
            'trans_username' => 'trans_username',
            'read_remark' => 'read_remark',
            'md5_sign' => 'md5_sign',
            'pick_at' => 'pick_at',
            'insert_at' => 'insert_at',
            'update_at' => 'update_at',
            'trade_cate' => 'trade_cate',

//            'id' => Yii::t('app/model', 'ID'),
//            'client_id' => Yii::t('app/model', 'Client ID'),
//            'client_code' => Yii::t('app/model', 'Client Code'),
//            'trade_type' => Yii::t('app/model', 'Trade Type'),
//            'trans_id' => Yii::t('app/model', 'Trans ID'),
//            'trans_account' => Yii::t('app/model', 'Trans Account'),
//            'trans_time' => Yii::t('app/model', 'Trans Time'),
//            'trans_type' => Yii::t('app/model', 'Trans Type'),
//            'trans_amount' => Yii::t('app/model', 'Trans Amount'),
//            'trans_status' => Yii::t('app/model', 'Trans Status'),
//            'trans_fee' => Yii::t('app/model', 'Trans Fee'),
//            'before_balance' => Yii::t('app/model', 'Before Balance'),
//            'trans_balance' => Yii::t('app/model', 'Trans Balance'),
//            'trans_remark' => Yii::t('app/model', 'Trans Remark'),
//            'trans_username' => Yii::t('app/model', 'Trans Username'),
//            'read_remark' => Yii::t('app/model', 'Read Remark'),
//            'md5_sign' => Yii::t('app/model', 'Md5 Sign'),
//            'pick_at' => Yii::t('app/model', 'Pick At'),
//            'insert_at' => Yii::t('app/model', 'Insert At'),
//            'update_at' => Yii::t('app/model', 'Update At'),
//            'trade_cate' => Yii::t('app/model', 'trade_cate'),
        ];
    }
}
