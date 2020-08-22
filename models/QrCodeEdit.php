<?php

namespace app\models;

use Yii;
use yii\base\Model;

class QrCodeEdit extends Model
{
    public $id;
    public $qr_address;
    public $qr_nickname;
    public $qr_account;
    public $qr_type;
    public $qr_status;
    public $qr_location;
    public $per_day_amount;
    public $per_day_orders;

    public function rules()
    {
        return [
            [['id', 'qr_status', 'per_day_amount', 'per_day_orders', 'qr_nickname', 'qr_account', 'qr_type', 'qr_location'], 'required'],
            ['qr_type', 'in', 'range' => [1, 2, 3, 4]],
            ['qr_status', 'in', 'range' => [1, 2, 9]],
            [['qr_nickname', 'qr_account', 'qr_location'], 'string', 'max' => 50],
            [['per_day_orders', 'id'], 'integer'],
            [['per_day_amount'], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'qr_address' => Yii::t('app/model', 'qr_status'),
            'qr_address' => Yii::t('app/model', 'qr_address'),
            'qr_nickname' => Yii::t('app/model', 'qr_nickname'),
            'qr_account' => Yii::t('app/model', 'qr_account'),
            'qr_type' => Yii::t('app/model', 'qr_type'),
            'qr_location' => Yii::t('app/model', 'qr_location'),
            'per_day_amount' => Yii::t('app/model', 'per_day_amount'),
            'per_day_orders' => Yii::t('app/model', 'per_day_orders'),
        ];
    }
}
