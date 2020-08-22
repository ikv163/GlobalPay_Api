<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "refund".
 *
 * @property int $id ID
 * @property string $order_id 平台订单号
 * @property string $photo 返款图片凭证
 * @property string $refund_money 回款金额
 * @property int $refund_type 返款类型 1全额返款 2已扣佣金返款
 * @property int $refund_status 返款状态 1待审核 2成功 3驳回
 * @property string $operator 审核人员
 * @property string $remark 备注
 */
class Refund extends \yii\db\ActiveRecord
{
    public $mch_order_id;
    public $username;
    public $qr_code;
    public $mch_name;
    public $order_type;
    public $order_fee;
    public $order_amount;
    public $actual_amount;
    public $order_status;
    public $is_settlement;
    public $insert_at_start;
    public $insert_at_end;
    public $wechat_rate;
    public $alipay_rate;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'refund';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['refund_money', 'wechat_rate', 'alipay_rate'], 'number'],
            [['mch_order_id', 'username', 'qr_code', 'mch_name', 'order_type', 'order_fee', 'order_amount', 'actual_amount', 'order_status', 'is_settlement', 'insert_at', 'update_at', 'insert_at_start', 'update_at_end', 'system_remark'], 'safe'],
            [['refund_type', 'refund_status'], 'integer'],
            [['order_id'], 'string', 'max' => 100],
            [['photo', 'remark'], 'string', 'max' => 255],
            [['operator'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'insert_at_start' => Yii::t('app/model', 'Insert At Start'),
            'insert_at_end' => Yii::t('app/model', 'Insert At End'),
            'insert_at' => Yii::t('app/model', 'Insert At'),
            'update_at' => Yii::t('app/model', 'Update At'),
            'is_settlement' => Yii::t('app/model', 'Is Settlement'),
            'order_status' => Yii::t('app/model', 'Order Status'),
            'actual_amount' => Yii::t('app/model', 'Actual Amount'),
            'order_amount' => Yii::t('app/model', 'Order Amount'),
            'order_fee' => Yii::t('app/model', 'Order Fee'),
            'order_type' => Yii::t('app/model', 'Order Type'),
            'mch_name' => Yii::t('app/model', 'Mch Name'),
            'id' => Yii::t('app/model', 'ID'),
            'order_id' => Yii::t('app/model', 'Order ID'),
            'photo' => Yii::t('app/model', 'Photo'),
            'refund_money' => Yii::t('app/model', 'Refund Money'),
            'refund_type' => Yii::t('app/model', 'Refund Type'),
            'refund_status' => Yii::t('app/model', 'Refund Status'),
            'operator' => Yii::t('app/model', 'Operator'),
            'remark' => Yii::t('app/model', 'Remark'),
            'mch_order_id' => Yii::t('app/model', 'mch_order_id'),
            'username' => Yii::t('app/model', 'Username'),
            'qr_code' => Yii::t('app/model', 'Qr Code'),
            'system_remark' => Yii::t('app/model', 'System_Remark'),
        ];
    }
}
