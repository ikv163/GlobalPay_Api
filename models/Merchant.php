<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "merchant".
 *
 * @property int $id
 * @property string $mch_name 商户名称
 * @property string $mch_code 商户英文简码
 * @property string $mch_key 商户密钥
 * @property int|null $mch_status 商户状态 0禁用 1启用
 * @property float $available_money 商户可提单总额，-1则表示不限额度
 * @property float|null $used_money 商户已提单金额
 * @property float|null $balance 商户余额
 * @property string|null $pay_password 商户资金密码（提现密码）
 * @property string|null $telephone 商户联系方式
 * @property float $wechat_rate 微信费率
 * @property float $alipay_rate 支付宝费率
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 更新时间
 * @property string|null $remark 备注
 */
class Merchant extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'merchant';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mch_status'], 'integer'],
            [['available_money', 'used_money', 'balance', 'wechat_rate', 'alipay_rate', 'union_pay_rate', 'bank_card_rate'], 'number'],
            [['insert_at', 'update_at'], 'safe'],
            [['mch_name', 'mch_code'], 'string', 'max' => 50],
            [['mch_key', 'pay_password', 'remark'], 'string', 'max' => 255],
            [['telephone'], 'string', 'max' => 20],
            [['mch_name'], 'unique'],
            [['mch_code'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mch_name' => 'Mch Name',
            'mch_code' => 'Mch Code',
            'mch_key' => 'Mch Key',
            'mch_status' => 'Mch Status',
            'available_money' => 'Available Money',
            'used_money' => 'Used Money',
            'balance' => 'Balance',
            'pay_password' => 'Pay Password',
            'telephone' => 'Telephone',
            'wechat_rate' => 'Wechat Rate',
            'alipay_rate' => 'Alipay Rate',
            'union_pay_rate' => 'union_pay_rate',
            'bank_card_rate' => 'bank_card_rate',
            'insert_at' => 'Insert At',
            'update_at' => 'Update At',
            'remark' => 'Remark',
        ];
    }
}
