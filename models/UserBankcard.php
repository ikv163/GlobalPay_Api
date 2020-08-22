<?php

namespace app\models;

use app\common\Common;
use Yii;

/**
 * This is the model class for table "user_bankcard".
 *
 * @property int $id
 * @property string $bankcard_number 银行卡卡号
 * @property string $bankcard_owner 银行卡所属人姓名
 * @property string $bank_code 银行类型编码
 * @property string $bankcard_address 开户行地址
 * @property string $username 用户名
 * @property int $user_type 用户类型 1商户 2收款员
 * @property int|null $card_status 是否默认使用 1可用 2禁用  9删除
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 修改时间
 */
class UserBankcard extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_bankcard';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['user_type', 'card_status'], 'integer'],
            [['insert_at', 'update_at'], 'safe'],
            [['bankcard_number', 'bank_code', 'username'], 'string', 'max' => 50],
            [['bankcard_owner'], 'string', 'max' => 30],
            [['bankcard_address'], 'string', 'max' => 255],
            [['bankcard_number'], 'unique'],
            ['user_type', 'in', 'range' => [1, 2]]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app/model', 'ID'),
            'bankcard_number' => Yii::t('app/model', 'Bankcard Number'),
            'bankcard_owner' => Yii::t('app/model', 'Bankcard Owner'),
            'bank_code' => Yii::t('app/model', 'Bank Code'),
            'bankcard_address' => Yii::t('app/model', 'Bankcard Address'),
            'username' => Yii::t('app/model', 'Username'),
            'user_type' => Yii::t('app/model', 'User Type'),
            'card_status' => Yii::t('app/model', 'Card Status'),
            'insert_at' => Yii::t('app/model', 'Insert At'),
            'update_at' => Yii::t('app/model', 'Update At'),
        ];
    }

    /**
     * @param $username
     * @param $userType
     * @return array|\yii\db\ActiveRecord[]|null
     * 根据用户名和用户类型查找银行卡
     */
    public static function queryUserBankcard($username, $userType)
    {
        $banks = UserBankcard::find()->where(['username' => $username, 'user_type' => $userType])->andWhere(['<', 'card_status', 9])->orderBy(['id' => SORT_DESC])->asArray()->all();
        if (!$banks) {
            return null;
        }
        foreach ($banks as $k => $bank) {
            $bank['bankName'] = Yii::t('app', 'BankTypes')[$bank['bank_code']]['BankTypeCode'];
            $banks[$k] = $bank;
        }
        return $banks;
    }

    /**
     * @param $data
     * @return array|\yii\db\ActiveRecord[]|null
     * 添加用户银行卡
     */
    public static function addBankcard($data)
    {
        $msg = '';
        $bank = new UserBankcard();
        $bank->load($data, '');
        if (!($bank->validate())) {
            $msg = Common::getModelError($bank);
            \Yii::error($data, 'UserBankcard_validateError');
        } else {
            if ($bank->save()) {
                $msg = 1;
            } else {
                $msg = '添加银行卡失败';
            }
        }
        return $msg;
    }
}
