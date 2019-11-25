<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

// TODO 限时功能模型 还没用到

namespace app\models;

use yii\db\ActiveRecord;

// TODO 功能暂未用到
/**
 * 限时数据
 *
 * example: id = user_vip_{identity_id}, data = buy|gift
 *
 * @property string $id 一般情况都是 prefix + id 不要滥用
 * @property string $data 功能数据
 * @property int $time_expire 过期时间
 *
 * @property-read bool $isExpired
 *
 * @author William Chan <root@williamchan.me>
 */
class Timeout extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'timeout';
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return strval($this->data);
    }

    /**
     * @return bool 是否过期
     */
    public function getIsExpired()
    {
        return time() > $this->time_expire;
    }

    /**
     * 取出数据
     * @param string $id
     * @return string|false 不存在返回 false
     */
    public static function get($id)
    {
        $model = static::findOne($id);
        if ($model === null || $model->getIsExpired()) {
            return false;
        }
        return $model->data;
    }

    /**
     * 删除数据
     * @param string $id
     * @return bool
     */
    public static function del($id)
    {
        return static::deleteAll(['id' => $id]) > 0;
    }

    /**
     * 按前缀取出全部
     * @param string $prefix 前缀
     * @return array
     */
    public static function getAll($prefix)
    {
        $result = [];
        $models = static::find()
            ->where(['like', 'id', $prefix . '%', false])
            ->andWhere(['>', 'time_expire', time()])
            ->all();
        foreach ($models as $model) {
            /* @var $model static */
            $key = substr($model->id, strlen($prefix));
            $result[$key] = $model->data;
        }
        return $result;
    }

    /**
     * 存入限时数据
     * @param string $id
     * @param int $ttl
     * @param string $data
     */
    public static function put($id, $ttl, $data = '')
    {
        $model = static::findOne($id);
        if ($model === null) {
            $model = new static([
                'id' => $id,
                'time_expire' => time() + $ttl,
                'data' => $data,
            ]);
        } else {
            $model->time_expire = time() + $ttl;
            $model->data = $data;
        }
        $model->save(false);
    }

    /**
     * @return int 删除数量
     */
    public static function purge()
    {
        return (int) static::deleteAll(['<', 'time_expire', time() - 864000]);
    }
}
