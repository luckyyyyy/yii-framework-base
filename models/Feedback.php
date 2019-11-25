<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\models;

use app\components\File;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * 反馈
 *
 * @property int $id
 * @property int $identity_id 用户ID
 * @property int $type 反馈类型
 * @property string $content 反馈内容
 * @property string $attachment 图片
 * @property int $isDeal 是否处理
 * @property string $ua 反馈用户ua
 * @property int $time_create 反馈时间
 *
 * @property-read Identity $identity Identity
 * @property-read typeLabel $typeLabel 反馈类型中文
 *
 * @author fangjiali
 */
class Feedback extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'feedback';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'content', 'identity_id', 'attachment'], 'required'],
            [['type'], 'in', 'range' => array_keys(static::typeLabels())],

        ];
    }

    /**
     * 将 type 转为中文, attachment 转为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        if (in_array($name, ['attachment'])) {
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
     * 关联 Identity
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id']);
    }

    /**
     * 获取typeLabel
     * @return mixed|string
     */
    public function getTypeLabel()
    {
        $typeLabels = static::typeLabels();
        $type = $this->getAttribute('type');
        return isset($typeLabels[$type]) ? $typeLabels[$type] : '';
    }


    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $attachment = $this->getAttribute('attachment');
            $value = [];
            foreach ($attachment as $item) {
                $value[] = File::findOne($item);
            }
            $this->attachment = Json::encode($value);
            $this->time_create = time();
            $this->ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
        }

        return parent::beforeSave($insert);
    }


    /**
     * type 反馈类型 中文
     * @return array
     */
    public static function typeLabels()
    {
        return [
            1 => '参数错误',
        ];
    }
}
