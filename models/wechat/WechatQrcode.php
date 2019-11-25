<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models\wechat;

use app\models\PageTrait;
use Yii;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;
use yii\helpers\Json;

/**
 * 微信公众平台带参数二维码
 *
 * @property int $id 参数
 * @property string $scene
 * @property int $media_id
 * @property string $hash 数据摘要 hash32
 * @property array $data 数据 Json 格式
 * @property string $type 二维码类型，暂时仅支持：QR_SCENE
 * @property string $ticket 二维码图片换取票据
 * @property string $url 扫描后的目标网址
 * @property int $time_expire 失效时间
 * @property int $count 计数
 * @property string $summary 摘要
 *
 * @property-read string $imageUrl 图像网址
 * @property-read bool $isValid 是否有效
 * @property-read WechatMedia $media
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatQrcode extends ActiveRecord
{
    use PageTrait;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['summary'], 'required'],
        ];
    }

    /**
     * 自动转换 data 属性数据为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        $value = parent::__get($name);
        if ($name === 'data' && is_string($value)) {
            $value = Json::decode($value);
            $this->setAttribute($name, $value);
        }
        return $value;
    }

    /**
     * 处理 data 属性转换为 json 串并计算 hash
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($name === 'data' && is_array($value)) {
            $json = Json::encode($value);
            $this->setAttributes('data', $json);
            $this->setAttributes('hash', md5($json));
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * @return string 二维码图片网址
     */
    public function getImageUrl()
    {
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($this->ticket);
    }

    /**
     * @return bool 是否有效
     */
    public function getIsValid()
    {
        return time() < $this->time_expire;
    }

    /**
     * 关联微信媒体库
     * @return \yii\db\ActiveQuery
     */
    public function getMedia()
    {
        return $this->hasOne(WechatMedia::class, ['id' => 'media_id']);
    }

    /**
     * 生成二维码
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1443433542
     * @param array $data 内部数据，其中 scene 表示永久场景值
     * @return static 包含此数据的对象
     */
    public static function loadFor(array $data)
    {
        $json = Json::encode($data);
        if (isset($data['scene'])) {
            $type = 'QR_LIMIT_STR_SCENE';
            $hash = md5($type . ':' . $json);
            $data['scene'] .= $hash;
            $scene = ['scene_str' => $data['scene']];
        } else {
            $type = 'QR_SCENE';
            $hash = md5($json);
            $scene = null;
        }
        // submit request
        $model = static::findOne(['hash' => $hash]);
        if ($model === null) {
            $model = new static([
                'media_id' => $data['media_id'] ?? 0,
                'scene' => $data['scene'] ?? '',
                'hash' => $hash,
                'data' => $json,
                'type' => $type,
                'summary' => $data['summary'] ?? null
            ]);
            $model->save(false);
        } elseif ($model->getIsValid()) {
            return $model;
        }
        if ($scene === null) {
            $scene = ['scene_id' => (int) $model->id];
        }

        // generate from remote server
        $wechat = Yii::$app->get('wechatApp');
        /* @var $wechat \app\components\WechatApp */
        $data = $wechat->api('qrcode/create', [
            'expire_seconds' => 86400 * 30,
            'action_name' => $model->type,
            'action_info' => ['scene' => $scene],
        ], 'POST_JSON');
        if (isset($data['ticket'])) {
            $model->updateAttributes([
                'ticket' => $data['ticket'],
                'time_expire' => isset($data['expire_second']) ? time() + $data['expire_seconds'] : 0,
                'url' => $data['url'],
            ]);
        }
        return $model;
    }

    /**
     * @return int 删除数量
     */
    public static function purge()
    {
        return (int) static::deleteAll(['and', ['type' => 'QR_SCENE'], ['<', 'time_expire', time() - 864000]]);
    }
}
