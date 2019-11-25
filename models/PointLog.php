<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use yii\db\ActiveRecord;
use yii\helpers\Json;

// TODO 功能暂未用到
/**
 * 积分日志
 *
 * @property int $id
 * @property int $identity_id 用户 id
 * @property int $num 变化额度，负值是消耗，正值为获得
 * @property int $time_create 变动时间
 * @property string $remark 变动说明
 * @property string $order_num 外部订单号
 * @property string $order_extra 外部订单原始数据
 *
 * @property-read string $summary 变动概要
 * @property array $extra 外部信息
 *
 * @author William Chan <root@williamchan.me>
 */
class PointLog extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'point_log';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['num'], 'integer'],
            [['remark', 'order_num'], 'string', 'max' => 255],
        ];
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
    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            Identity::updateAllCounters(['point' => $this->num], ['id' => $this->identity_id]);
        }
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @return string 消费概要，remark 的头行
     */
    public function getSummary()
    {
        $remark = trim($this->remark);
        $pos = strpos($remark, "\n");
        return $pos === false ? $remark : rtrim(substr($remark, 0, $pos));
    }

    /**
     * @return array 外部订单原始数据
     */
    public function getExtra()
    {
        return empty($this->order_extra) ? [] : Json::decode($this->order_extra);
    }

    /**
     * 设置外部订单原始数据
     * @param array $values
     */
    public function setExtra($values)
    {
        $this->order_extra = Json::encode($values);
    }

    /**
     * 添加积分记录
     * @param Identity|int $identity 用户对象或 id
     * @param int $num 积分值
     * @param string $remark
     */
    public static function add($identity, $num, $remark)
    {
        $identity_id = $user instanceof Identity ? $identity->id : intval($identity);
        $model = new static([
            'identity_id' => $identity_id,
            'num' => $num,
            'remark' => $remark,
        ]);
        return $model->save(false);
    }
}
