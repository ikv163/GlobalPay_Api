<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "log_record".
 *
 * @property int $id
 * @property string|null $route 路由
 * @property string|null $info 日志信息
 * @property string|null $username 用户名
 * @property int|null $user_type 用户类型 1平台 2商户 3收款员
 * @property string|null $log_time 添加时间
 */
class LogRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'log_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['info'], 'string'],
            [['user_type'], 'integer'],
            [['log_time'], 'safe'],
            [['route', 'username'], 'string', 'max' => 50],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'route' => 'Route',
            'info' => 'Info',
            'username' => 'Username',
            'user_type' => 'User Type',
            'log_time' => 'Log Time',
        ];
    }
}
