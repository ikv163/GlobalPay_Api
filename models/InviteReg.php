<?php
namespace app\models;

use app\common\Common;
use Yii;
use yii\validators\NumberValidator;
use app\models\Cashier;

class InviteReg extends \yii\db\ActiveRecord
{

    public $confirm_password;
    public $verify_code;
    public $error;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'cashier';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['username', 'login_password', 'confirm_password', 'invite_code', 'verify_code'], 'required'],
            [['username'], 'string', 'max' => 50],
            [['username'], 'unique'],
            [['login_password'], 'string', 'min'=>6, 'max' => 20],
            [['invite_code'], 'string', 'max' => 10],
            ['invite_code', 'in', 'range' => Cashier::find()->select('invite_code')->asArray()->column(), 'message'=>\Yii::t('app/error', 'invalid_invite_code')],
            ['confirm_password', 'compare', 'compareAttribute' => 'login_password', 'message'=>\Yii::t('app/error', 'confirm_password_error')],
            [['confirm_password'], 'safe'],
            [['verify_code'], 'captcha', 'message'=>\Yii::t('app/error', 'captcha_error')],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'invite_code' => Yii::t('app/model', 'invite_code'),
            'username' => Yii::t('app/model', 'usernameX'),
            'login_password' => Yii::t('app/model', 'login_password'),
            'confirm_password' => Yii::t('app/model', 'confirm_password'),
            'verify_code' => Yii::t('app/model', 'captcha'),
            'error' => Yii::t('app/error', 'error'),
        ];
    }

}