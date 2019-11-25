<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\components\cloud;

use app\components\Muggle;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * 阿里云
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class Aliyun extends BaseObject implements CloudInterface
{
    /**
     * @var bool 是否启用
     */
    public $enabled = true;

    /**
     * @var string API 密钥信息
     */
    public $accessKeyId = '';
    public $accessKeySecret = '';

    /**
     * 阿里云图片处理基础url
     * @var string
     */
    public $baseUrl = 'https://dtplus-cn-shanghai.data.aliyuncs.com/image/';

    /**
     * @var string 鉴黄
     */
    public $pornDetectSuffix = 'porn';

    /**
     * @var string 图像打标
     */
    public $imageTagSuffix = 'tag';

    /**
     * @var string 场景识别
     */
    public $imageSceneSuffix = 'scene';



    /**
     * 传图片至图像处理接口
     * @param $image_url
     * @param string $suffix
     * @return int|string
     */
    public function handelImage($image_url, $suffix)
    {
        if ($this->enabled === false) {
            return 0;
        }
        $body = Json::encode([
            'type' => 0,
            'image_url' => $image_url,
        ]);
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
        ];
        $headers['Authorization'] = 'Dataplus ' . $this->accessKeyId . ':' . $this->genAccessSign($this->baseUrl . $suffix, $body, $headers);
        $res = Muggle::httpRequest('POST', $this->baseUrl . $suffix, $body, $headers);
        return $res;
    }

    /**
     * 鉴黄
     * @inheritdoc
     * @see https://help.aliyun.com/knowledge_detail/53537.html
     */
    public function pornDetect($url)
    {
        $res = $this->handelImage($url, $this->pornDetectSuffix);
        if ($res !== false) {
            $res = Json::decode($res);
            if (isset($res['tags'][0]['value'])) {
                $value = $res['tags'][0]['value'];
                if ($value === '色情') {
                    return 1;
                } elseif ($value === '性感') {
                    return 2;
                }
            }
        }
        return 0;
    }

    /**
     * 图像打标
     * @inheritdoc
     */
    public function imageTag($url)
    {
        $res = $this->handelImage($url, $this->imageTagSuffix);
        if ($res !== false) {
            $res = Json::decode($res);
            return isset($res['tags']) ? $res['tags']: 0;
        }
        return 0;
    }

    /**
     * 场景识别
     * @inheritdoc
     */
    public function imageScene($url)
    {
        $res = $this->handelImage($url, $this->imageSceneSuffix);
        if ($res !== false) {
            $res = Json::decode($res);
            $result = [];
            if (isset($res['tags'])) {
                foreach ($res['tags'] as $item) {
                    $result [] = $item['value'];
                }
            }
            return $result;
        }
        return 0;
    }


    /**
     * @inheritdoc
     */
    public function textSensitivity($content, $type = 1)
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function textKeywords($content, $title = null)
    {
        return [];
    }

    /**
     * @param string $url
     * @param string $body
     * @param array $headers
     * @return string api签名
     */
    private function genAccessSign($url, $body, $headers)
    {
        if (($pos = strpos($url, '/', 10)) !== false) {
            $url = substr($url, $pos);
        }
        $md5 = base64_encode(md5($body, true));
        $str = implode("\n", ['POST', $headers['Accept'], $md5, $headers['Content-Type'], $headers['Date'], $url]);
        return base64_encode(hash_hmac('SHA1', $str, $this->accessKeySecret, true));
    }
}
