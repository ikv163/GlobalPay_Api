<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "pay_channel_relation".
 *
 * @property int $id
 * @property int $channel_id 支付渠道ID
 * @property string $qr_code 简码名称
 * @property string $insert_at 添加时间
 * @property string $operator 操作者
 * @property string $unique_mark 唯一标识
 */
class PayChannelRelation extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'pay_channel_relation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['channel_id'], 'integer'],
            [['insert_at'], 'required'],
            [['insert_at'], 'safe'],
            [['qr_code', 'operator'], 'string', 'max' => 50],
            [['unique_mark'], 'string', 'max' => 40],
            [['unique_mark'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'channel_id' => 'Channel ID',
            'qr_code' => 'Qr Code',
            'unique_mark' => 'unique_mark',
            'insert_at' => 'Insert At',
            'operator' => 'Operator',
        ];
    }
}
