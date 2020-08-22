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
class MerchantQuery extends Model
{
    public $mch_code;
    public $mch_order_id;
    public $sign;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['mch_code','sign',  'mch_order_id'], 'required'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'mch_code' => Yii::t('app/model', 'mch_code'),
            'mch_order_id' => Yii::t('app/model', 'mch_order_id'),
            'sign' => Yii::t('app/model', 'sign'),
        ];
    }
}
