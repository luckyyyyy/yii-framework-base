<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * 通行证黑名单
 *
 * @property int $id
 * @property int $flag 黑名单标记
 * @property int $time_create 添加时间
 *
 * @property array $flags 黑名单标记
 * @property-read bool $isGlobal 是否是全局黑名单用户
 * @property-read Identity $identity 通行证
 * @property-read string $summary 概要
 *
 * @author William Chan <root@williamchan.me>
 */
class Block extends ActiveRecord
{
    const FLAG_GLOBAL = 0x01; // 全局黑名单
    const FLAG_TESTING = 0x02; // 众测黑名单
    const FLAG_COMMUNITY = 0x04; // 社区黑名单

    use FlagTrait;
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'block';
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $attributes = ['flags'];
        if (Yii::$app->user->isSuper) {
            $attributes[] = 'isGlobal';
        }
        return [self::SCENARIO_DEFAULT => $attributes];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_create = time();
        }
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->flag === 0) {
            if ($this->isNewRecord) {
                return true;
            } else {
                return $this->delete() !== false;
            }
        }
        return parent::save($runValidation, $attributeNames);
    }

    /**
     * 关联帐号
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'id']);
    }

    /**
     * 是否是全局黑名单
     * @return bool
     */
    public function getIsGlobal()
    {
        return $this->hasFlag(self::FLAG_GLOBAL);
    }

    /**
     * @param int $value
     * @return bool 是否是全局黑名单
     */
    public function setIsGlobal($value)
    {
        if ($value) {
            $this->addFlag(self::FLAG_GLOBAL);
        } else {
            $this->removeFlag(self::FLAG_GLOBAL);
        }
    }

    /**
     * 获取摘要
     * @return string 摘要
     */
    public function getSummary()
    {
        if ($this->getIsGlobal()) {
            return '全局黑名单';
        } else {
            $parts = [];
            foreach (static::flagOptions() as $flag => $label) {
                if ($this->hasFlag($flag)) {
                    $parts[] = $label;
                }
            }
            return implode('、', $parts);
        }
    }

    /**
     * 判断是否是黑名单
     * @param string $name 黑名单flag
     * @return bool
     */
    public function canBlocked($name)
    {
        return $this->getIsGlobal() || $this->hasFlag(strtr($name, ['-' => '_', '/' => '_']));
    }

    /**
     * 载入模型
     * @param int|Identity $id
     * @return static
     */
    public static function loadFor($id)
    {
        $_id = $id instanceof Identity ? $id->id : $id;
        $model = static::findOne(['id' => $_id]);
        if ($model === null) {
            $model = new static(['id' => $_id]);
            $model->loadDefaultValues();
        }
        if ($id instanceof Identity) {
            $model->populateRelation('identity', $id);
        }
        return $model;
    }

    /**
     * 创建模型
     * @param Identity $identity
     * @return static
     */
    public static function create(Identity $identity)
    {
        $model = new static([
            'id' => $identity->id,
        ]);
        $model->loadDefaultValues();
        $model->populateRelation('identity', $identity);
        return $model;
    }

    /**
     * 将字符串转换为标记
     * @param string $flag
     * @return int
     */
    public static function permFlag($flag)
    {
        return is_int($flag) ? $flag : constant('self::FLAG_' . strtoupper($flag));
    }

    /**
     * 黑名单标记定义
     * @return array
     */
    public static function flagOptions()
    {
        return [
            self::FLAG_TESTING => '众测黑名单',
            self::FLAG_COMMUNITY => '社区黑名单',
        ];
    }
}
