<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Alarm 告警/提醒 暂时不需要API
 *
 * @property int $id
 * @property int $flag 权限位
 *
 * @property array $flags 权限数组
 * @property-read Identity $identity 平台帐号
 *
 * @author William Chan <root@williamchan.me>
 */
class Alarm extends ActiveRecord
{
    const FLAG_ALL = 0x01; // 全部接收
    const FLAG_WECHAT_STAT_CHAPING = 0x2;
    const FLAG_WECHAT_STAT_ZHONGCE = 0x4;
    const FLAG_WECHAT_STAT_HEISHI = 0x8;
    const FLAG_WECHAT_ALARM_CHAPING = 0x10;

    use FlagTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'alarm';
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
     * 获取全部标记的用户
     * @param int $flags
     * @return void
     */
    public static function getAllIds($flags)
    {
        $flags |= self::FLAG_ALL;
        return array_column(static::find()
            ->select('id')
            ->where(['&', 'flag', $flags])
            ->asArray()
            ->all(), 'id');
    }

    /**
     * FLAG 定义
     * @return array
     */
    public static function flagOptions()
    {
    }
}
