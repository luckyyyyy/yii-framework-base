<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\models\wechat;

use yii\db\ActiveRecord;

/**
 * 微信文章阅读量统计
 *
 * @property int $id
 * @property int $msgid 消息ID
 * @property array $data 数据
 * @property int $time_create
 *
 * @author fangjiali
 */
class WechatMp extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            if (is_array($this->data)) {
                $this->data = Json::encode($this->data);
            }
            $this->time_create = strtotime(date('Y-m-d H:i', time()));
        }
        return parent::beforeSave($insert);
    }
}
