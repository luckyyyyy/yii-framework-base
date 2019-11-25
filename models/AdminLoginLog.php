<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * 管理员登陆日志
 *
 * @property int $id
 * @property int $identity_id
 * @property string $method 登陆方式
 * @property string $states 请求的数据
 * @property string $ip
 * @property string $agent 访问客户端
 * @property string $time 时间
 *
 * @property-read Identity $identity 通行证用户

 * @author William Chan <root@williamchan.me>
 */
class AdminLoginLog extends ActiveRecord
{
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'admin_login_log';
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time = time();
        }
        foreach (['states'] as $key) {
            $value = $this->getAttribute($key);
            if (is_array($value)) {
                $this->setAttribute($key, Json::encode($value));
            }
        }
        return parent::beforeSave($insert);
    }

    /**
     * 自动将 states 转换为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        if (in_array($name, ['states'])) {
            $value = $this->getAttribute($name);
            if (!is_array($value)) {
                $value = Json::decode($value);
                $value = is_array($value) ? $value : [];
                $this->setAttribute($name, $value);
            }
            return $value;
        }
        return parent::__get($name);
    }

    /**
     * 关联 通行证
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id']);
    }
}
