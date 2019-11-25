<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\components\Muggle;
use Yii;
use yii\base\InvalidCallException;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * 登录令牌
 *
 * @property string $id AccessToken
 * @property int $user_id 绑定的用户 id
 * @property string $push_id 推送 token (小程序则记录原始id)
 * @property string $ip 客户端 IP
 * @property string $os_name App 系统名称
 * @property string $os_version 系统版本
 * @property string $device 硬件型号
 * @property array $states 状态数据 (如：微信小程序返回的 session_key 等)
 * @property int $time_create 创建时间
 * @property int $time_update 更新时间
 *
 * @property Identity $identity 绑定通行证用户
 *
 * @author William Chan <root@williamchan.me>
 */
class Token extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'token';
    }

    /**
     * 自动将 states 转换为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($name === 'states') {
            $value = $this->getAttribute($name);
            if (!is_array($value)) {
                $this->setAttribute($name, $value = Json::decode($value));
            }
            return $value;
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['push_id'], 'string', 'max' => 100],
            [['os_name', 'os_version', 'device'], 'string', 'max' => 50],
        ];
    }
    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_create = $this->time_update = time();
            $this->ip = Muggle::clientIp();
        }
        $value = $this->getAttribute('states');
        if (is_array($value)) {
            $this->setAttribute('states', Json::encode($value));
        }
        return parent::beforeSave($insert);
    }

    /**
     * 更新时间和 IP
     * @inheritdoc
     */
    public function afterFind()
    {
        parent::afterFind();
        $now = time();
        if (($now - $this->time_update) > 1800) {
            $this->updateAttributes([
                'time_update' => $now,
                'ip' => Muggle::clientIp(),
            ]);
        }
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();
        if (!empty($this->push_id)) {
            // @todo
        }
    }

    /**
     * 关联 Identity
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'user_id'])->inverseOf('token');
    }

    /**
     * 绑定用户并保存
     * @param \app\models\Identity $identity
     */
    public function setIdentity(Identity $identity)
    {
        if (!$this->isNewRecord) {
            throw new InvalidCallException('Only new token can set identity.');
        }
        // kick other token with same OS type
        $condition = [
            'user_id' => $identity->id,
            'os_name' => $this->os_name,
            'device' => $this->device,
        ];
        static::deleteAll($condition);
        $query = static::find();
        do {
            $this->id = Yii::$app->security->generateRandomString(48);
        } while ($query->where(['id' => $this->id])->exists());
        $this->link('identity', $identity);
    }
}
