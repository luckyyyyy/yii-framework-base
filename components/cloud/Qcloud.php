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
 * 腾讯云
 * @author hightman <hightman@cloud-sun.com>
 */
class Qcloud extends BaseObject // implements CloudInterface
{
    /**
     * @var bool 是否启用
     */
    public $enabled = true;

    /**
     * @var string 项目 ID
     */
    public $appId = '';

    /**
     * @var string 项目密钥信息
     */
    public $appSecretId = '';
    public $appSecretKey = '';

    /**
     * @var string 空间名称
     */
    public $bucket = 'hmpub';

    /**
     * @var string 空间绑定域名
     */
    public $bucketHost;

    /**
     * @var string API 密钥信息
     */
    public $apiSecretId = '';
    public $apiSecretKey = '';

    /**
     * @var string 鉴黄接口
     */
    public $pornDetectUrl = '';

    /**
     * @var string 文智接口
     */
    public $wenzhiUrl = 'https://wenzhi.api.qcloud.com/v2/index.php';

    /**
     * @var string 缩图样式及分割符
     */
    private $_thumbStyle = '/small';

    /**
     * @inheritdoc
     */
    public function getThumbStyle()
    {
        return $this->_thumbStyle;
    }

    /**
     * 设置缩图样式及分割符
     * @param string $value
     */
    public function setThumbStyle($value)
    {
        $this->_thumbStyle = $value;
    }

    /**
     * @inheritdoc
     */
    public function getFileUrl($url)
    {
        $url = Url::to($url);
        if ($this->enabled === true) {
            if (strpos($url, '://') !== false) {
                $url = substr($url, strpos($url, '/', 10));
            }
            if ($this->bucketHost === null) {
                $url = 'https://' . $this->bucket . '-' . $this->appId . '.image.myqcloud.com' . $url;
            } else {
                $url = 'https://' . $this->bucketHost . $url;
            }
        }
        return $url;
    }

    /**
     * @inheritdoc
     */
    public function getImageUrl($url, $thumb = true)
    {
        $url = $this->getFileUrl($url);
        if ($thumb === true) {
            $url .= $this->getThumbStyle();
        }
        return $url;
    }

    /**
     * @inheritdoc
     * @see https://www.qcloud.com/document/product/275/6101
     */
    public function pornDetect($url)
    {
        if ($this->enabled === false) {
            return 0;
        }
        $params = [
            'appid' => $this->appId,
            'bucket' => $this->bucket,
            'url' => $url,
        ];
        $headers = ['Authorization' => $this->getPornDetectSign()];
        $res = Muggle::httpRequest('POST_JSON', $this->pornDetectUrl, $params, $headers);
        if ($res !== false) {
            $res = Json::decode($res);
            if (isset($res['data']['porn_score']) && $res['data']['porn_score'] > 80) {
                return 2;
            } elseif (isset($res['data']['result'])) {
                return (int) $res['data']['result'];
            }
        }
        return 0;
    }

    /**
     * @inheritdoc
     * @see https://www.qcloud.com/document/product/271/2615
     */
    public function textSensitivity($content, $type = 1)
    {
        $res = $this->wenzhiRequest('TextSensitivity', ['type' => $type, 'content' => $content]);
        return $res ? $res['sensitive'] : 0;
    }

    /**
     * @inheritdoc
     * @see https://www.qcloud.com/document/product/271/2074
     */
    public function textKeywords($content, $title = null)
    {
        $content = trim($content);
        if ($title === null) {
            $pos = strpos($content, "\n");
            $title = substr($content, 0, $pos === false ? strlen($content) : $pos);
        }
        $res = $this->wenzhiRequest('TextKeywords', ['title' => $title, 'content' => $content]);
        $keywords = [];
        if (isset($res['keywords'])) {
            foreach ($res['keywords'] as $word) {
                $keywords[] = $word['keyword'];
            }
        }
        return $keywords;
    }

    /**
     * @inheritdoc
     * @see https://cloud.tencent.com/document/product/271/2071
     */
    public function textLexicalAnalysis($content, $type = 1)
    {
        $res = $this->wenzhiRequest('LexicalAnalysis', ['type' => $type, 'text' => $content, 'code' => 0x00200000]);
        return $res;
    }

    /**
     * 文智接口请求
     * @param string $action 接口类型
     * @param array $params 附加参数
     * @return array 鉴定结果
     */
    private function wenzhiRequest($action, $params = [])
    {
        // params & signature
        $params += [
            'Action' => $action,
            'Nonce' => mt_rand(),
            'Region' => 'hz',
            'SecretId' => $this->apiSecretId,
            'Timestamp' => time(),
        ];
        // signature
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . strtr($value, '_', '.');
        }
        $params['Signature'] = base64_encode(hash_hmac('sha1', 'GET' . substr($this->wenzhiUrl, 8) . '?' . implode('&', $parts), $this->apiSecretKey, true));
        $data = Muggle::httpRequest('GET', $this->wenzhiUrl, $params);
        if ($data !== false) {
            $data = Json::decode($data);
            if (isset($data['code']) && $data['code'] === 0) {
                return $data;
            }
        }
        return false;
    }

    /**
     * @return string 鉴黄签名
     */
    private function getPornDetectSign()
    {
        $now = time();
        $expired = $now + 300;
        $str = 'a=' . $this->appId . '&b=' . $this->bucket . '&k=' . $this->appSecretId . '&t=' . $now . '&e=' . $expired;
        return base64_encode(hash_hmac('SHA1', $str, $this->appSecretKey, true) . $str);
    }
}
