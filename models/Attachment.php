<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\components\Html;
use Yii;
use yii\db\ActiveRecord;

/**
 * 附件表
 *
 * @property int $id
 * @property int $identity_id 上传的用户ID
 * @property string $type 分类（后台用）
 * @property string $name 附件的原始名字
 * @property string $size 附件的原始名字
 * @property string $path 对象路径
 * @property string $mime MIME
 * @property int $time_create 时间
 *
 * @property-read string $url 真实可访问的url地址
 *
 * @author William Chan <root@williamchan.me>
 */
class Attachment extends ActiveRecord
{
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'attachment';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['mime'], 'string', 'max' => 50],
            [['type'], 'string', 'max' => 10],
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
     * 真实可访问的url地址
     * @return string
     */
    public function getUrl()
    {
        return Html::extUrl($this->path);
    }
}
