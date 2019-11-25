<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\models\wechat;

use yii\db\ActiveRecord;

/**
 * 微信小程序专用的推送id
 *
 * @property int $id
 * @property int $user_id 微信用户ID
 * @property string $formid
 * @property int $count 剩余使用次数
 * @property int $time_create 创建时间 7天过期
 *
 * @property-write int $type formid 类型
 *
 * @author William Chan <root@williamchan.me>
 * @see https://developers.weixin.qq.com/miniprogram/dev/api/notice.html#%E4%BD%BF%E7%94%A8%E8%AF%B4%E6%98%8E
 */
class WechatFormid extends ActiveRecord
{
    const TYPE_FORM = 1; // 普通场景下 只能被发送1次
    const TYPE_PREPAY = 2; // 支付场景下 可以被发送3次

    public $type;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['formid'], 'required'],
            [['formid'], 'string', 'max' => 64],
            [['formid'], 'validateFormid'],
            [['type'], 'in', 'range' => [self::TYPE_FORM, self::TYPE_PREPAY]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_create = time();
            if ($this->type === self::TYPE_FORM) {
                $this->count = 1;
            } elseif ($this->type === self::TYPE_PREPAY) {
                $this->count = 3;
            }
        }
        return parent::beforeSave($insert);
    }

    /**
     * 查找这个用户的formid（小程序推送用）
     * @return null|string
     */
    public static function findOneByUser($id, $autoDelete = true)
    {
        try {
            $model = static::find()
                ->where(['user_id' => $id])
                ->andWhere(['>', 'time_create', time() - 86400 * 7])
                ->orderBy(['time_create' => SORT_ASC])
                ->one();
            if ($model) {
                $formid = $model->formid;
                if ($autoDelete) {
                    if ($model->count > 1) {
                        $model->updateCounters(['count' => -1]);
                    } else {
                        $model->delete();
                    }
                }
                return $formid;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 效验formid
     * @return void
     */
    public function validateFormid()
    {
        if ($this->formid === 'the formId is a mock one') {
            $this->addError('formid', 'the formId is a mock one, skip.');
        }
    }

    /**
     * @return int 删除过期数据
     */
    public static function purge()
    {
        return (int) static::deleteAll(['<', 'time_create', time() - 86400 * 7]);
    }
}
