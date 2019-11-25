<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models\wechat;

use app\models\PageTrait;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\base\InvalidParamException;

/**
 * 微信关键词表
 *
 * @property int $id
 * @property string $is_match 是否模糊匹配 存入redis 优先级降低
 * @property string $keyword 关键词
 * @property int $media_id
 * @property int $count 命中次数
 *
 * @property bool $isMatch
 * @property-read WechatMedia $media
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatKeyword extends ActiveRecord
{
    const WECHAT_HELLO_KEYWORD = 'WECHAT_HELLO_KEYWORD';
    const WECHAT_ANY_KEYWORD = 'WECHAT_ANY_KEYWORD';

    use PageTrait;

    private static $cacheId = 'cache';
    private static $cachePrefix = 'keyword.match.';

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['is_match', 'keyword', 'media_id'], 'required'],
            [['is_match'], 'in', 'range' => ['Y', 'N'], 'skipOnEmpty' => false],
            [['keyword'], 'string', 'max' => 20],
            [['keyword'], 'trim'],
            [['isMatch'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        static::clear();
        return parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        static::clear();
        return parent::afterDelete();
    }

    /**
     * 是否为模糊匹配
     * @return bool
     */
    public function getIsMatch()
    {
        return $this->is_match === 'Y';
    }

    /**
     * 设置是否模糊匹配
     */
    public function setIsMatch($val)
    {
        $this->is_match = $val === true ? 'Y' : 'N';
    }

    /**
     * 关联微信媒体库
     * @return \yii\db\ActiveQuery
     */
    public function getMedia()
    {
        return $this->hasOne(WechatMedia::class, ['id' => 'media_id']);
    }

    /* ------------------------------------- 模型无关 ------------------------------------- */

    /**
     * 获取缓存key
     * @return string
     */
    private static function getCacheKey()
    {
        return $key = self::$cachePrefix . Yii::$app->db->tablePrefix;
    }

    /**
     * 获取或者保存关键词结果
     */
    public static function cache($data = null)
    {
        $key = static::getCacheKey();
        $cache = Yii::$app->get(static::$cacheId);
        if ($data) {
            $cache->set($key, $data, 86400);
        } else {
            return $cache->get($key);
        }
    }

    /**
     * 删除缓存
     */
    private static function clear()
    {
        $key = static::getCacheKey();
        $cache = Yii::$app->get(static::$cacheId);
        $cache->delete($key);
    }
}
