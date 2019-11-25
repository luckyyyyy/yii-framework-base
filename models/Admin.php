<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * 管理员
 *
 * @property int $id
 * @property int $flag 权限位
 * @property int $time_create 添加时间
 * @property int $time_expire 过期时间
 *
 * @property array $flags 权限数组
 * @property-read bool $isSuper 是否为超级管理
 * @property-read Identity $identity 平台帐号
 * @property-read int $dayLeft 任期天数
 * @property-read string $summary 权限概要
 *
 * @author William Chan <root@williamchan.me>
 */
class Admin extends ActiveRecord
{
    const FLAG_SUPER = 0x1;
    const FLAG_WECHAT = 0x2; // 微信管理员
    // const FLAG_TESTING = 0x8; // 众测管理员
    // const FLAG_APP_MARKER = 0x10; // app marker管理
    // const FLAG_WEBSITE = 0x20; // 官网管理
    // const FLAG_SHOP = 0x40;    // 商城管理
    // const FLAG_ADVERTISE = 0x80; // 广告管理
    // const FLAG_CONSOLE = 0x100; // 分销运营人员
    // const FLAG_CUSTOMER = 0x200; // 客服人员
    // const FLAG_FINANCE = 0x400; // 财务人员

    use FlagTrait;
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'admin';
    }

    /**
     * 管理别名
     * @var array
     */
    public static $nameAliases = [
        1 => 'XX',
    ];

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $attributes = ['flags', 'dayLeft'];
        if (Yii::$app->user->isRoot) {
            $attributes[] = 'isSuper';
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
     * @return bool 是否为超级管理
     */
    public function getIsSuper()
    {
        return $this->hasFlag(self::FLAG_SUPER);
    }

    /**
     * @param int $value
     * @return bool 是否为超级管理
     */
    public function setIsSuper($value)
    {
        if ($value) {
            $this->addFlag(self::FLAG_SUPER);
        } else {
            $this->removeFlag(self::FLAG_SUPER);
        }
    }

    /**
     * @return int 剩余有效天数
     */
    public function getDayLeft()
    {
        if ($this->isNewRecord) {
            return 30;
        } elseif ($this->getIsSuper()) {
            return 99;
        } else {
            $left = ceil(($this->time_expire - time()) / 86400);
            return $left > 0 ? $left : 0;
        }
    }

    /**
     * 设置有效天数
     * @param int $value 天数
     */
    public function setDayLeft($value)
    {
        $this->time_expire = time() + $value * 86400;
    }

    /**
     * @return string 摘要
     */
    public function getSummary()
    {
        if ($this->getIsSuper()) {
            return '超级管理';
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
     * 判断是否有权限
     * @param string|int $name 权限名称
     * @return bool
     */
    public function canAdmin($name)
    {
        if ($this->getIsSuper()) {
            return true;
        }
        if (is_int($name)) {
            return $this->hasFullFlag($name);
        } else {
            return $this->hasFlag(strtr($name, ['-' => '_', '/' => '_']));
        }
    }

    /**
     * 判断是否有权限位
     * @return bool
     */
    public function isAdmin()
    {
        // @fixme 不要和上面的 canAdmin 合并，需要保证严谨性。
        return $this->flag !== 0;
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
     * 将字符串转换为权限位
     * @param string $flag
     * @return int
     */
    public static function permFlag($flag)
    {
        return is_int($flag) ? $flag : constant('self::FLAG_' . strtoupper($flag));
    }

    /**
     * 转换管理员名字
     * @param int $id
     * @return string
     */
    public static function name($id)
    {
        static $cache = [];
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        if (isset(self::$nameAliases[$id])) {
            $name = self::$nameAliases[$id];
        } else {
            $name = Identity::find()
                ->select('name')
                ->where(['id' => $id])
                ->createCommand()
                ->queryScalar();
            if (empty($name)) {
                $name = '#' . $id;
            }
        }
        return $cache[$id] = $name;
    }

    /**
     * 权限标记定义
     * @return array
     */
    public static function flagOptions()
    {
        return [
            self::FLAG_WECHAT => '微信管理',
            // self::FLAG_TESTING => '众测管理',
            // self::FLAG_APP_MARKER => 'APP推荐管理',
            // self::FLAG_WEBSITE => '官网管理',
            // self::FLAG_SHOP => '商城管理',
            // self::FLAG_ADVERTISE => '广告管理',
            // self::FLAG_CONSOLE => '分销管理',
            // self::FLAG_CUSTOMER => '客服人员',
            // self::FLAG_FINANCE => '财务人员',
        ];
    }
}
