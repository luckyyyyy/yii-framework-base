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
use yii\helpers\Url;

/**
 * 短网址转换
 *
 * @property string $id
 * @property string $url 完整的原URL
 * @property int $time_create 创建时间
 * @property int $count 点击次数
 * @property-read string $realUrl 真实网址
 * @property-read array $shortUrl 短网址数组
 *
 * @author William Chan <root@williamchan.me>
 */
class ShortUrl extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'shorturl';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['url'], 'required'],
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
     * @return string
     */
    public function __toString()
    {
        return $this->getShortUrl();
    }

    /**
     * @return string 短网址
     */
    public function getShortUrl()
    {
        return Yii::$app->params['host.shortUrl'] . $this->id;
    }

    /**
     * @return string 真实网址
     */
    public function getRealUrl()
    {
        $cache = Yii::$app->cache;
        $cacheKey = 'shorturl.' . $this->id;
        $url = $cache->get($cacheKey);
        if ($url === false) {
            $ttl = 3600;
            $url = $this->url;
            // if (strpos($url, '.qq.com/') !== false && strpos($url, '/play?s=') !== false) {
            //     // 全民K歌
            //     $data = Muggle::httpRequest('GET', $url, [], ['user-agent' => 'hm-url converter']);
            //     if ($data !== false && ($pos1 = strpos($data, '{"shareid":')) !== false) {
            //         $pos2 = strpos($data, '};', $pos1);
            //         $data = Json::decode(substr($data, $pos1, $pos2 - $pos1 + 1));
            //         if (isset($data['detail']['playurl']) && !empty($data['detail']['playurl'])) {
            //             $url = $data['detail']['playurl'];
            //         }
            //     }
            // }
            $cache->set($cacheKey, $url, $ttl);
        }
        return $url;
    }

    /**
     * 计算哈希
     * @param string $input
     * @return array
     */
    public static function hashes($input)
    {
        $base32 = [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h',
            'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p',
            'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
            'y', 'z', '0', '1', '2', '3', '4', '5',
        ];
        $hex = md5($input);
        $hexLen = strlen($hex);
        $subHexLen = $hexLen / 8;
        $output = [];
        for ($i = 0; $i < $subHexLen; $i++) {
            $subHex = substr($hex, $i * 8, 8);
            $int = 0x3FFFFFFF & hexdec($subHex);
            $out = '';
            for ($j = 0; $j < 6; $j++) {
                $val = 0x0000001F & $int;
                $out .= $base32[$val];
                $int = $int >> 5;
            }
            $output[] = $out;
        }
        return $output;
    }

    /**
     * 创建短网址
     * @param string $url
     * @return static
     */
    public static function create($url)
    {
        $ids = static::hashes($url);
        foreach ($ids as $i => $id) {
            $model = static::findOne($id);
            if ($model === null) {
                $model = new static(['id' => $id, 'url' => $url]);
                $model->save(false);
            } elseif ($model->url !== $url && $i < 3) {
                continue;
            } else {
                $model->updateAttributes(['url' => $url]);
            }
            return $model;
        }
    }
}
