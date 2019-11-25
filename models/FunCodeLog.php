<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\components\Muggle;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 * 激活码使用日志（仅作为常规记录用于查询，对逻辑并无影响，限制用户只能使用1次的记录）
 *
 * @property string $id 激活码
 * @property string $orig_data 原数据，使用时记录
 * @property string $ip 客户端 IP 使用时 ip
 * @property int $identity_id 使用者 id
 * @property int $time_use 使用时间
 *
 * @property Identity $identity 使用者
 * @property FunCode $funCode 激活码
 *
 * @author William Chan <root@williamchan.me>
 */
class FunCodeLog extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'funcode_log';
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_use = time();
            $this->ip = Muggle::clientIp();
        }
        return parent::beforeSave($insert);
    }

    /**
     * 使用者
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id']);
    }

    /**
     * 关联绑定的使用者
     * @return \yii\db\ActiveQuery
     */
    public function getBind()
    {
        return $this->hasOne(FunCode::class, ['id' => 'id']);
    }

    /**
     * 记录激活码使用日志
     * @param FunCode $funCode
     * @return bool
     */
    public static function record(FunCode $funCode)
    {
        $model = new static([
            'id' => $funCode->id,
            'identity_id' => $funCode->identity->id,
        ]);
        $model->loadDefaultValues();
        $model->populateRelation('funCode', $funCode);
        $model->populateRelation('identity', $funCode->identity);
        $model->orig_data = $funCode->orig_data;
        return $model->save(false);
    }
}
