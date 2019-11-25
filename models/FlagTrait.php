<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * 旗标位处理公共代码
 *
 * @author William Chan <root@williamchan.me>
 */
trait FlagTrait
{
    /**
     * 判断属性上是否有某个旗标 (bit)
     * @param int|string $flag 旗标值或名称
     * @return bool
     */
    public function hasFlag($flag)
    {
        return $this->flag & $this->intFlagBit($flag) ? true : false;
    }

    /**
     * 判断属性上是否完全拥有某些旗标组合
     * @param int $flag 旗标组合值
     * @return bool
     */
    public function hasFullFlag($flag)
    {
        $flag = $this->intFlagBit($flag);
        return ($this->flag & $flag) === $flag ? true : false;
    }

    /**
     * 添加旗标
     * @param int|string $flag 旗标值或名称
     * @param bool $sync 是否同步写入数据库
     */
    public function addFlag($flag, $sync = false)
    {
        $flag = $this->intFlagBit($flag);
        $this->flag |= $flag;
        if ($sync && $this instanceof ActiveRecord) {
            $this->updateAll(['flag' => new Expression('flag | ' . $flag)], $this->getOldPrimaryKey(true));
        }
    }

    /**
     * 移除旗标
     * @param int|string $flag 旗标值或名称
     * @param bool $sync 是否同步写入数据库
     */
    public function removeFlag($flag, $sync = false)
    {
        $flag = $this->intFlagBit($flag);
        $this->flag &= ~$flag;
        if ($sync === true && $this instanceof ActiveRecord) {
            $this->updateAll(['flag' => new Expression('flag & ~' . $flag)], $this->getOldPrimaryKey(true));
        }
    }

    /**
     * 切换旗标
     * @param int|string $flag 旗标值或名称
     * @param bool $sync 是否同步写入数据库
     */
    public function toggleFlag($flag, $sync = false)
    {
        $flag = $this->intFlagBit($flag);
        $this->flag ^= $flag;
        if ($sync === true && $this instanceof ActiveRecord) {
            $this->updateAll(['flag' => new Expression('flag ^ ' . $flag)], $this->getOldPrimaryKey(true));
        }
    }

    /**
     * @param int|string $flag 旗标值或名称
     * @return int
     */
    private function intFlagBit($flag)
    {
        if (is_int($flag)) {
            return $flag;
        }
        $key = 'self::FLAG_' . strtoupper($flag);
        return defined($key) ? constant($key) : 0;
    }

    /**
     * 设置标记组
     * @param array $values
     */
    public function setFlags($values)
    {
        if (is_array($values)) {
            $options = static::flagOptions();
            foreach ($values as $bit => $value) {
                $bit = intval($bit);
                if (!isset($options[$bit])) {
                    continue;
                } else {
                    if ($value['have'] === true) {
                        $this->addFlag($bit);
                    } else {
                        $this->removeFlag($bit);
                    }
                }
            }
        }
    }

    /**
     * 获取标记组
     * have (bool) 代表是否设置
     * 管理员显示全部标记
     * ```json
     * {
     *     "0x1": {
     *         "have": true,
     *         "label": "超级管理员",
     *     },
     *     "0x2": {
     *         "have": false,
     *         "label": "微信管理员",
     *     }
     * }
     * ```
     * @param bool $labels 是否强制显示label 有些label不能给用户看
     * @return array
     */
    public function getFlags($labels = false)
    {
        $flags = [];
        $isAdmin = Yii::$app->user->isAdmin('%');
        if (!$isAdmin && method_exists(static::class, 'safeFlagOptions')) {
            foreach (static::safeFlagOptions() as $flag => $label) {
                $have = $this->hasFlag($flag);
                $dat = ['have' => $have];
                if ($labels) {
                    $dat['label'] = $label;
                }
                $flags[$flag] = $dat;
            }
        } else {
            foreach (static::flagOptions() as $flag => $label) {
                $have = $this->hasFlag($flag);
                if ($have || $isAdmin) {
                    $dat = ['have' => $have];
                    if ($labels || $isAdmin) {
                        $dat['label'] = $label;
                    }
                    $flags[$flag] = $dat;
                }
            }
        }
        return $flags;
    }

    /**
     * 获取标记组（hash）内部使用 不要给前端
     * @return array
     */
    public function getFlagsHash()
    {
        $flags = [];
        foreach (static::flagOptions() as $flag => $label) {
            $have = $this->hasFlag($flag);
            $flags[$flag] = $have;
        }
        return $flags;
    }

    /**
     * Proxy 成员对象 和 flag 互转
     * 例如 isHot <=> self::FLAG_HOT
     * @param $name
     * @return bool
     */
    protected function proxyGetFlags($name)
    {
        static $exists = null;
        if ($exists === null) {
            $exists = property_exists(static::class, '_proxyFlags');
        }
        if ($exists) {
            if (isset(self::$_proxyFlags[$name])) {
                return $this->hasFlag(self::$_proxyFlags[$name]);
            }
        }
    }

    /**
     * Proxy 成员对象 和 flag 互转
     * 例如 isHot <=> self::FLAG_HOT
     * @return bool
     */
    protected function proxySetFlags($name, $value)
    {
        static $exists = null;
        if ($exists === null) {
            $exists = property_exists(static::class, '_proxyFlags');
        }
        if ($exists) {
            if (isset(self::$_proxyFlags[$name])) {
                if ($value === true) {
                    $this->addFlag(self::$_proxyFlags[$name]);
                } else {
                    $this->removeFlag(self::$_proxyFlags[$name]);
                }
                return true;
            }
        }
        return false;
    }
}
