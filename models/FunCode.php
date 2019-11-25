<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

// TODO 功能暂未完善
use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * 激活码
 *
 * @property string $id 激活码
 * @property int $type 类型
 * @property int $create_id 创建者ID 0表示系统创建
 * @property int $bind_id 绑定使用者id 0表示不限制使用的用户
 * @property int $time_create 创建时间
 * @property int $time_expire 过期时间
 * @property int $count 可以使用的次数（无限次数不受此影响）
 *
 * @property bool $isUnlimit 是否是无限次数
 * @property string $feature 功能特性
 * @property-read $orig_data 使用后的原始数据
 * @property-read bool $isExpired 是否已过期
 * @property-read bool $isUsed 是否已被使用
 * @property-read Identity $bind 绑定的使用者信息
 * @property-read FunCodeLog[] $logs 所有使用者信息
 * @property-read FunCodeLog $log 用户使用信息
 *
 * @property Identity $identity 当前使用者
 *
 * @author William Chan <root@williamchan.me>
 */
class FunCode extends ActiveRecord
{
    use FlagTrait;

    const TYPE_SINGLE = 0; // 一次性激活码（只影响提示信息）
    const TYPE_MILTI = 1; // 多次激活码（只影响提示信息）
    const TYPE_UNLIMIT = 2; // 无限次数

    const SCENARIO_USE = 'use';

    /**
     * @var string 使用后的原始数据
     */
    public $orig_data;

    private $_feature;
    private $_identity;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'funcode';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'required', 'on' => [self::SCENARIO_USE]],
            [['feature'], 'in', 'range' => array_keys(static::featureDefines()), 'on' => [self::SCENARIO_DEFAULT]],
            [['bind_id'], 'number'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['feature', 'isUnlimit', 'bind_id', 'count'],
            self::SCENARIO_USE => ['id'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        if ($this->getScenario() === self::SCENARIO_USE) {
            $pk = 'id';
            // 必须要有使用者
            if (!$this->identity) {
                $this->addError($pk, '没有使用对象');
                return false;
            }
            // 模型强验证 是否有效
            if ($this->isNewRecord && !$this->refresh()) {
                $this->addError($pk, '激活码不存在');
                return false;
            } elseif ($this->bind_id !== 0 && $this->identity->id !== $this->bind_id) {
                $this->addError($pk, '激活码不存在');
                return false;
            } elseif ($this->isUsed) {
                if ($this->type === self::TYPE_MILTI) {
                    $this->addError($pk, '激活码可被兑换的次数已达上限');
                } elseif ($this->type === self::TYPE_SINGLE) {
                    $this->addError($pk, '激活码已被使用');
                }
                return false;
            } elseif ($this->isExpired) {
                $this->addError($pk, '激活码已经过期');
                return false;
            } elseif ($this->getLog()->one()) { // 这里的处理对高并发非常危险 不想加互斥锁 所以下面有日志复合索引强验证
                $this->addError($pk, '您已经使用过该激活码了');
                return false;
            }
            // TODO 检查自身可使用条件
            // $feature = $this->getFeature();
            // if ($feature === 'xxx') {

            // }
        }
        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_create = time();
            if (!$this->time_expire) {
                $this->time_expire = time() + 30 * 86400;
            }
            if ($this->isUnlimit) {
                $this->count = 0;
            }
            if ($this->count === null) {
                $this->count = 1;
            }
            if ($this->count === 1) {
                $this->type = self::TYPE_SINGLE;
            } else {
                $this->type = self::TYPE_MILTI;
            }
        }
        if ($this->getScenario() === self::SCENARIO_USE) {
            // 先写日志后激活功效，保证数据完整性和一切不确定因素。
            FunCodeLog::record($this);
            /** @notice 不要修改表达式 */
            if (!$this->isUnlimit) {
                $this->count = new Expression('count - 1');
            }
        }
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        if ($this->getScenario() === self::SCENARIO_USE && $this->identity !== null) {
            // 成功使用后的功效
            // TODO
            // $feature = $this->getFeature();
            // if (substr($feature, 0, strlen('testingPoint')) === 'testingPoint') {

            // }
        }
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * 关联绑定的使用者
     * @return \yii\db\ActiveQuery
     */
    public function getBind()
    {
        return $this->hasOne(Identity::class, ['id' => 'bind_id']);
    }

    /**
     * 关联所有使用日志
     * @return \yii\db\ActiveQuery
     */
    public function getLogs()
    {
        return $this->hasMany(FunCodeLog::class, ['id' => 'id']);
    }

    /**
     * 关联当前使用者日志
     * @return \yii\db\ActiveQuery
     */
    public function getLog()
    {
        return $this->hasOne(FunCodeLog::class, ['id' => 'id'])
            ->andWhere(['identity_id' => $this->identity->id]);
    }

    /**
     * 功能特性（英文）
     * @return string
     */
    public function getFeature()
    {
        if ($this->_feature === null && ($pos = strpos($this->id, '_')) !== false) {
            $this->_feature = substr($this->id, 0, $pos);
        }
        return $this->_feature;
    }

    /**
     * 设定功能特性（英文）
     * @param string $value
     */
    public function setFeature($value)
    {
        $this->_feature = $value;
        $query = static::find();
        do {
            $this->id = $value . '_' . trim(Yii::$app->security->generateRandomString(16), '-_');
        } while ($query->where(['id' => $this->id])->exists());
    }

    /**
     * 获取功能特性名称（中文）
     * @return string
     */
    public function getLabel()
    {
        return $this->getDefine('label');
    }

    /**
     * 获取功能特性详细描述
     * @return string
     */
    public function getDescription()
    {
        return $this->getDefine('description');
    }

    /**
     * 是否过期
     * @return bool
     */
    public function getIsExpired()
    {
        return $this->time_expire < time();
    }

    /**
     * 是否已被使用（0次就算已被使用）
     * @return bool
     */
    public function getIsUsed()
    {
        return !$this->isUnlimit && $this->count === 0;
    }

    /**
     * 关联使用者
     * @return Identity $identity
     */
    public function getIdentity()
    {
        return $this->_identity;
    }

    /**
     * @param Identity $identity
     */
    public function setIdentity(Identity $identity)
    {
        $this->_identity = $identity;
    }

    /**
     * 设置成是否是无限激活次数
     * @param bool $isUnlimit
     */
    public function setIsUnlimit($isUnlimit)
    {
        if ($isUnlimit) {
            $this->type = self::TYPE_UNLIMIT;
            $this->count = 0;
        }
    }

    /**
     * @return bool 是否是无限激活次数的激活码
     */
    public function getIsUnlimit()
    {
        return $this->type === self::TYPE_UNLIMIT;
    }

    /**
     * 获取功能定义
     * @param string $key
     * @return mixed
     */
    public function getDefine($key = null)
    {
        $feature = $this->getFeature();
        $defines = static::featureDefines();
        if (!isset($defines[$feature])) {
            return null;
        }
        $define = $defines[$feature];
        if ($key === null) {
            return $define;
        } else {
            return $define[$key] ?? null;
        }
    }

    /**
     * 功能定义表
     * @return array
     */
    public static function featureDefines()
    {
        return [
            'testing-point' => [
                'label' => '增加众测抽奖机会',
                'description' => '增加最新一期众测的抽奖机会，随机1-5次。',
            ],
        ];
    }
}
