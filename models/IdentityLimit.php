<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

// TODO 功能暂未用到
/**
 * 通行证限制
 *
 * @property int $id 用户 id
 * @property string $day 日期
 * @property int $week 本周签到数据
 * @property int $day_flag 当天的标记

 * @property bool $isSigned 今日是否已签到
 * @property-read array $weekSigned 本周签到数据
 *
 * @author William Chan <root@williamchan.me>
 */
class IdentityLimit extends ActiveRecord
{
    const SIGN_POINT = 0;   // 签到积分
    const SIGN_POINT4 = 5;  // 每周签4天送积分
    const SIGN_POINT7 = 10;  // 每周签7天送积分

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'identity_limit';
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        $this->initDay();
        parent::afterFind();
    }

    /**
     * @return bool 今日是否已签到
     */
    public function getIsSigned()
    {
        return $this->getWeekSigned(intval(date('w')));
    }

    /**
     * 标记今日已签到
     * @param bool $value
     * @param int $addPoint
     */
    public function setIsSigned($value, &$addPoint = null)
    {
        $wday = intval(date('w'));
        if ($value !== $this->getWeekSigned($wday)) {
            $this->week ^= 1 << $wday;
            if ($this->isAttributeChanged('day')) {
                $this->save(false);
            } else {
                $this->updateAttributes(['week']);
            }
            if ($value === true) {
                // add sign point
                $addPoint = self::SIGN_POINT;
                $wnum = count($this->getWeekSigned());
                if ($wnum === 4) {
                    $addPoint += self::SIGN_POINT4;
                } elseif ($wnum === 7) {
                    $addPoint += self::SIGN_POINT7;
                }
                if ($addPoint > 0) {
                    PointLog::add($this->id, $addPoint, '连续签到' . $wnum . '天');
                }
            }
        }
    }

    /**
     * 获取周签到数据
     * @param int $wday 判断某天是否已签到
     * @return array|bool 周天为索引
     */
    public function getWeekSigned($wday = null)
    {
        $signed = $this->week & 0xff;
        if ($wday !== null) {
            $wday = 1 << intval($wday);
            return $signed & $wday ? true : false;
        } else {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $wday = 1 << $i;
                if ($signed & $wday) {
                    $days[$i] = true;
                }
            }
            return $days;
        }
    }

    /**
     * 添加今日标记
     * @param string|int $flag
     */
    public function addDayFlag($flag)
    {
        $flag = $this->intFlagBit($flag);
        if (!$this->hasDayFlag($flag)) {
            $this->day_flag |= $flag;
            $this->save(false);
        }
    }

    /**
     * @param string $type
     * @param int $num
     */
    protected function addDayNum($type, $num = 1)
    {
        $name = 'day_' . $type;
        if ($this->isAttributeChanged('day')) {
            $this->setAttribute($name, $this->getAttribute($name) + $num);
            $this->save(false);
        } else {
            $this->updateCounters([$name => $num]);
        }
    }

    /**
     * 初始化当日数据
     */
    protected function initDay()
    {
        $today = date('Y-m-d');
        if ($today !== $this->day) {
            $this->day = $today;
            // check week
            $week = intval(date('W'));
            if ($week !== (intval($this->week) >> 8)) {
                $this->week = $week << 8;
            }
        }
    }

    /**
     * @param int|string $flag 每日旗标值或名称
     * @return int
     */
    private function intFlagBit($flag)
    {
        if (is_int($flag)) {
            return $flag;
        }
        $key = 'self::DAY_' . strtoupper($flag);
        return defined($key) ? constant($key) : 0;
    }

    /**
     * 载入模型
     * @param Identity $identity 用户
     * @return static
     */
    public static function loadFor($identity)
    {
        $identity_id = $identity instanceof Identity ? $identity->id : $identity;
        $model = static::findOne(['id' => $identity_id]);
        if ($model === null) {
            $model = new static([
                'id' => $identity_id,
            ]);
            $model->afterFind();
        }
        if ($identity instanceof Identity) {
            $model->populateRelation('identity', $identity);
        }
        return $model;
    }
}
