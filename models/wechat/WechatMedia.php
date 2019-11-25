<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models\wechat;

use app\models\FlagTrait;
use app\models\PageTrait;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\base\InvalidParamException;

/**
 * 微信素材库
 *
 * @property int $id
 * @property int $flag 标记
 * @property int $type 类型
 * @property string|array $data 数据
 * @property int $time_modify 最后修改时间
 *
 * @property-read array $wechatCustomerFormat 客服消息的格式
 * @property-read string $wechatCustomerType 微信消息类型
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatMedia extends ActiveRecord
{
    use PageTrait;
    use FlagTrait;

    const FLAG_DELETE = 0x2; // 删除标记

    // 素材类型
    const TYPE_TEXT = 0; // 文本
    const TYPE_NEWS = 1; // 图文
    const TYPE_IMAGE = 2; // 图片
    const TYPE_VOICE = 3; // 语音
    const TYPE_MUSIC = 4; // 音乐
    const TYPE_VIDEO = 5; // 视频
    const TYPE_APP = 6; // 小程序
    const TYPE_LINK = 7; // 小程序图文

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'wechat_media';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['data'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['type', 'data', 'summary'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($name === 'data') {
            $value = $this->getAttribute($name);
            if (!is_array($value)) {
                try {
                    $this->setAttribute($name, $value = Json::decode($value));
                } catch (InvalidParamException $e) {
                }
            }
            return $value;
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        $message = $this->getAttribute('data');
        if (is_array($message)) {
            $this->setAttribute('data', Json::encode($message));
        }
        $this->time_modify = time();
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();
        WechatKeyword::deleteAll(['media_id' => $this->id]);
        WechatQrcode::deleteAll(['media_id' => $this->id]);
    }

    /**
     * 将媒体格式化成微信客服消息的格式
     * @param int $type 指定消息类型
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140547
     * @return array
     */
    public function getWechatCustomerFormat($type = null)
    {
        switch ($type ?? $this->type) {
            case self::TYPE_NEWS: // 图文
                $items = ['articles' => []];
                foreach ((array) $this->data as $item) {
                    $items['articles'][] = [
                        'title' => $item['msg'] ?? '',
                        'description' => $item['desc'] ?? '',
                        'url' => $item['url'] ?? '',
                        'picurl' => $item['pic'] ?? '',
                    ];
                }
                return $items;
            case self::TYPE_LINK: // 小程序图文
                $item = (array) $this->data[0];
                $data = [
                    'title' => $item['msg'] ?? '',
                    'description' => $item['desc'] ?? '',
                    'url' => $item['url'] ?? '',
                    'thumb_url' => $item['pic'] ?? '',
                ];
                return $data;
            case self::TYPE_TEXT: // 文字
                return ['content' => (string) $this->data];
            case self::TYPE_IMAGE: // 图片
                return ['media_id' => Yii::$app->wechatApp->uploadMedia($this->wechatCustomerType, (string) $this->data)];
            case self::TYPE_VOICE: // 语音
                return ['media_id' => Yii::$app->wechatApp->uploadMedia($this->wechatCustomerType, (string) $this->data)];
            case self::TYPE_VIDEO: // 视频
            case self::TYPE_MUSIC: // 音乐
            case self::TYPE_APP: // 小程序
                $wechat = Yii::$app->wechatApp;
                $scenario = $wechat->getScenarios($this->data['scenario']);
                if (isset($scenario['appid'])) {
                    $appid = $scenario['appid'];
                    return [
                        'appid' => $appid,
                        'title' => (string) $this->data['title'],
                        'pagepath' => (string) $this->data['pagepath'],
                        'thumb_media_id' => (string) $wechat->uploadMedia('image', (string) $this->data['thumb']),
                    ];
                }
        }
        return new \stdClass;
    }

    /**
     * 获取微信媒体消息类型
     * 消息类型，
     * 文本为text，
     * 图片为image，
     * 语音为voice，
     * 视频消息为video，
     * 音乐消息为music，
     * 图文消息（点击跳转到外链）为news，
     * 图文消息（点击跳转到图文消息页面）为mpnews，
     * 卡券为wxcard，
     * 小程序为miniprogrampage
     * 小程序图文为link
     * @param int $type 指定消息类型
     * @return string
     */
    public function getWechatCustomerType($type = null)
    {
        switch ($type ?? $this->type) {
            case self::TYPE_NEWS: // 图文
                return 'news';
            case self::TYPE_TEXT: // 文字
                return 'text';
            case self::TYPE_IMAGE: // 图片
                return 'image';
            case self::TYPE_VOICE: // 语音
                return 'voice';
            case self::TYPE_VIDEO: // 视频
                return 'video';
            case self::TYPE_MUSIC: // 音乐
                return 'music';
            case self::TYPE_APP: // 小程序
                return 'miniprogrampage';
            case self::TYPE_LINK: // 小程序图文
                return 'link';
        }
        return 'none';
    }
}
