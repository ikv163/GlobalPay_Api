<?php

namespace app\models;

use Yii;
use yii\base\Model;

/**
 * LoginForm is the model behind the login form.
 *
 * @property User|null $user This property is read-only.
 *
 */
class MerchantOrder extends Model
{
    public $mch_code;
    public $mch_order_id;
    public $order_type;
    public $order_amount;
    public $callback_url;
    public $notify_url;
    public $order_time;
    public $user_ip;
    public $sign;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['mch_code', 'sign', 'user_ip', 'mch_order_id', 'order_type', 'order_amount', 'callback_url', 'notify_url', 'order_time'], 'required'],
            [['order_amount'], 'number'],
            [['user_ip'], 'ip'],
            [['callback_url', 'notify_url'], 'url'],
            ['order_type', 'in', 'range' => [1, 2, 3, 4, 101, 102, 103, 104]]
        ];
    }

    public function attributeLabels()
    {
        return [
            'mch_code' => Yii::t('app/model', 'mch_code'),
            'mch_order_id' => Yii::t('app/model', 'mch_order_id'),
            'order_type' => Yii::t('app/model', 'order_type'),
            'order_amount' => Yii::t('app/model', 'order_amount'),
            'callback_url' => Yii::t('app/model', 'callback_url'),
            'notify_url' => Yii::t('app/model', 'notify_url'),
            'order_time' => Yii::t('app/model', 'order_time'),
            'user_ip' => Yii::t('app/model', 'user_ip'),
        ];
    }
}
