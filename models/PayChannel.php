<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "pay_channel".
 *
 * @property int $id
 * @property string $channel_name 渠道名称
 * @property int $pay_type 支付方式 1网银 2支付宝转卡 3微信转卡 4手机号转账
 * @property string $per_max_amount 每笔最大可收金额
 * @property string $per_min_amount 每笔最小可收金额
 * @property int $channel_status 渠道状态 0关闭  1启用
 * @property string $user_level 用户等级
 * @property string $credit_level 信用等级
 * @property string $update_at 上次修改时间
 * @property string $insert_at 添加时间
 */
class PayChannel extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'pay_channel';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['pay_type', 'channel_status'], 'integer'],
            [['per_max_amount', 'per_min_amount'], 'number'],
            [['update_at', 'insert_at'], 'required'],
            [['update_at', 'insert_at'], 'safe'],
            [['channel_name'], 'string', 'max' => 30],
            [['user_level', 'credit_level'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'channel_name' => '渠道名称',
            'pay_type' => '支付类型',
            'per_max_amount' => '每笔最大',
            'per_min_amount' => '每笔最小',
            'channel_status' => '渠道状态',
            'user_level' => '用户等级',
            'credit_level' => '信用等级',
            'update_at' => '更新时间',
            'insert_at' => '添加时间',
        ];
    }
}
