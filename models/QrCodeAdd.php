<?php

namespace app\models;

use Yii;
use yii\base\Model;

class QrCodeAdd extends Model
{
    public $qr_address;
    public $qr_address_manual;
    public $qr_nickname;
    public $qr_account;
    public $qr_type;
    public $qr_location;
    public $per_day_amount;
    public $per_day_orders;
    public $real_name;
    public $telephone;
    public $bank_card_number;
    public $bank_code;
    public $bank_address;

    public function rules()
    {
        return [
            [['per_day_amount', 'per_day_orders', 'qr_nickname', 'qr_account', 'qr_type', 'qr_location'], 'required'],
            [['qr_address', 'qr_address_manual', 'real_name', 'telephone', 'bank_card_number', 'bank_code', 'bank_address'], 'safe'],
            ['qr_type', 'in', 'range' => [1, 2, 3, 4]],
            [['qr_nickname', 'qr_account', 'qr_location'], 'string', 'max' => 50],
            [['per_day_orders'], 'integer'],
            [['per_day_amount'], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'qr_address' => Yii::t('app/model', 'qr_address'),
            'qr_nickname' => Yii::t('app/model', 'qr_nickname'),
            'qr_account' => Yii::t('app/model', 'qr_account'),
            'qr_type' => Yii::t('app/model', 'qr_type'),
            'qr_location' => Yii::t('app/model', 'qr_location'),
            'per_day_amount' => Yii::t('app/model', 'per_day_amount'),
            'per_day_orders' => Yii::t('app/model', 'per_day_orders'),
            'qr_address_manual' => Yii::t('app/model', 'qr_address_manual'),
            'real_name' => Yii::t('app/model', 'real_name'),
            'telephone' => Yii::t('app/model', 'telephone'),
            'bank_card_number' => Yii::t('app/model', 'bank_card_number'),
            'bank_code' => Yii::t('app/model', 'bank_code'),
            'bank_address' => Yii::t('app/model', 'bank_address'),
        ];
    }
}
