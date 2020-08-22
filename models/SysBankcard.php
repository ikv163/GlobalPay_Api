<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "sys_bankcard".
 *
 * @property int $id
 * @property string $bankcard_number 银行卡卡号
 * @property string $bankcard_owner 银行卡所属人姓名
 * @property string $bank_code 银行类型编码
 * @property string $bankcard_address 开户行地址
 * @property float|null $balance 银行卡余额
 * @property int|null $card_status 状态 0默认使用 1可用 2禁用  9删除
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 修改时间
 */
class SysBankcard extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_bankcard';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['balance'], 'number'],
            [['card_status'], 'integer'],
            [['insert_at', 'update_at', 'last_order_time', 'last_money_time'], 'safe'],
            [['bankcard_number', 'bank_code'], 'string', 'max' => 50],
            [['bankcard_owner'], 'string', 'max' => 30],
            [['bankcard_address'], 'string', 'max' => 255],
            [['bankcard_number'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'bankcard_number' => Yii::t('app', 'Bankcard Number'),
            'bankcard_owner' => Yii::t('app', 'Bankcard Owner'),
            'bank_code' => Yii::t('app', 'Bank Code'),
            'bankcard_address' => Yii::t('app', 'Bankcard Address'),
            'balance' => Yii::t('app', 'Balance'),
            'card_status' => Yii::t('app', 'Card Status'),
            'insert_at' => Yii::t('app', 'Insert At'),
            'update_at' => Yii::t('app', 'Update At'),
            'last_order_time' => Yii::t('app', 'Last Order Time'),
            'last_money_time' => Yii::t('app', 'Last Money Time'),
        ];
    }


    /**
     * 获取系统收款银行卡
     * @return mixed
     */
    public static function getSysDepositBankcard(){
        return SysBankcard::find()->where('card_status=:status', array(':status'=>1))->orderBy('last_order_time ASC, last_money_time ASC')->asArray()->one();
    }
}
