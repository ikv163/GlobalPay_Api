<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "system_config".
 *
 * @property int $id
 * @property string $config_name 配置名称
 * @property string $config_code 配置识别码
 * @property string $config_value 配置值
 * @property int $config_status 配置状态 0禁用 1启用 2删除
 * @property string|null $insert_at 添加时间
 * @property string|null $update_at 修改时间
 * @property string|null $remark 备注
 */
class SystemConfig extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'system_config';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['config_status'], 'integer'],
            [['insert_at', 'update_at'], 'safe'],
            [['config_name', 'config_code'], 'string', 'max' => 50],
            [['config_value', 'remark'], 'string', 'max' => 255],
            [['config_code'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'config_name' => 'Config Name',
            'config_code' => 'Config Code',
            'config_value' => 'Config Value',
            'config_status' => 'Config Status',
            'insert_at' => 'Insert At',
            'update_at' => 'Update At',
            'remark' => 'Remark',
        ];
    }


    /**
     * @param $configCode
     * 获取系统配置
     */
    public static function getSystemConfig($configCode)
    {
        $config = null;
        try {
            if (isset($configCode) && $configCode != null) {
                $keyName = 'config_' . $configCode;
                $config = Yii::$app->redis->get($keyName);
                $config = null;
                if ($config == null) {
                    $temp = SystemConfig::find()->where(['config_code' => $configCode, 'config_status' => 1])->select(['config_value'])->one();
                    if ($temp) {
                        Yii::$app->redis->set($keyName, $temp['config_value']);
                        $config = $temp['config_value'];
                    }
                }
            }
            return $config;
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), $configCode . '获取配置发生异常');
            return $config;
        }
    }

}
