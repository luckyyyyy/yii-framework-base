<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\models\activity;

use yii\db\ActiveRecord;

/**
 * 活动 - 清空蚂蚁花呗
 *
 * @property int $id
 * @property int $identity_id
 * @property string $message
 * @property int $time_create 创建时间 7天过期
 *
 * @property-read Identity $identity 通行证信息
 *
 * @author William Chan <root@williamchan.me>
 */
class AlipayAntCreditPay extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'activity_alipay_ant_credit_pay';
    }

    /**
     * 关联 Identity
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['identity_id' => 'id']);
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
}
