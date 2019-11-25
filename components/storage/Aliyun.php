<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\components\storage;

use app\components\Muggle;
use app\components\Html;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * 阿里云OSS
 * @see https://help.aliyun.com/document_detail/31978.html
 * @author William Chan <root@williamchan.me>
 */
class Aliyun extends BaseObject implements StorageInterface
{
    /**
     * @var string 空间名称
     */
    public $bucket;

    /**
     * @var bool 是否是内网环境
     */
    public $internal = false;

    /**
     * @var string 空间节点
     */
    public $bucketPoint;

    /**
     * CDN 域名 不绑定则为 {bucket}.{endPoint}
     * @var string 空间绑定域名
     */
    public $bucketHost;

    /**
     * @var string API 密钥信息
     */
    public $accessKeyId;
    public $accessKeySecret;

    /**
     * 错误信息
     */
    private $_error;

    /**
     * 获取完整的 EndPoint
     * @return string
     */
    public function getEndPoint()
    {
        $sub = $this->bucket . '.oss-' . $this->bucketPoint;
        if ($this->internal) {
            $sub = $sub . '-internal';
        }
        return $sub . '.aliyuncs.com';
    }

    /**
     * @inheritdoc
     */
    public function getHost()
    {
        if (!$this->bucketHost) {
            return 'https://' . $this->getEndPoint();
        } else {
            return $this->bucketHost;
        }
    }

    /**
     * @inheritdoc
     */
    public function getLastError()
    {
        return $this->_error;
    }

    /**
     * 获取阿里云OSS的图片信息
     * @param string $url
     * @return void
     */
    public function getImageInfo($path)
    {
        $method = 'GET';
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
        ];
        $path = $path . '?x-oss-process=image/info';
        $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
        $api = $this->getFullUrlPath($path);
        $res = $this->sendRequest($method, $api, $headers);
        return $res['raw'];
    }

    /**
     * 获取图片主色调
     * @param string $url
     * @return void
     */
    public function getImageHue($path)
    {
        $method = 'GET';
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
        ];
        $path = $path . '?x-oss-process=image/average-hue';
        $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
        $api = $this->getFullUrlPath($path);
        $res = $this->sendRequest($method, $api, $headers);
        return $res['raw'];
    }

    /**
     * @inheritdoc
     */
    public function copyObject($path, $source, $Header = null)
    {
        $method = 'PUT';
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
            'x-oss-copy-source' => $this->getFullObjectPath($source),
        ];
        $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
        $api = $this->getFullUrlPath($path);
        $res = $this->sendRequest($method, $api, $headers);
        return $res['status'];
    }

    /**
     * @inheritdoc
     */
    public function deleteObject($path, $Header = null)
    {
        $method = 'DELETE';
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
        ];
        $api = $this->getFullUrlPath($path);
        $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
        $res = $this->sendRequest($method, $api, $headers);
        if ($res['code'] === 204) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function headObject($path, $Header = null)
    {
        $method = 'HEAD';
        $headers = [
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
        ];
        $api = $this->getFullUrlPath($path);
        $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
        $res = $this->sendRequest($method, $api, $headers);
        if ($res['code'] === 200) {
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function putObject($path, $content, $Header = null)
    {
        try {
            $body = substr($content, 0, 4) === 'http' ? Muggle::guzzleHttpRequest('GET', $content, [], [ 'basic' => true ]) : $content;
            $method = 'PUT';
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($info, $body);
            finfo_close($info);
            $headers = [
                'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Content-Type' => $mimeType,
            ];
            $api = $this->getFullUrlPath($path);
            $headers['Authorization'] = $this->getAuthorization($method, $path, $headers);
            if ($body) {
                $res = $this->sendRequest($method, $api, $headers, $body);
                return $res['status'];
            }
            return $this->setErrors('put content empty.');
        } catch (\Exception $e) {
            $this->setErrors($e->getMessage());
        }
    }

    /**
     * 生成url签名
     * @see https://help.aliyun.com/document_detail/31952.html
     * @param string $object 文件对象
     */
    public function getAccessUrl($object)
    {
        $expires = time() + 60 * 60;
        $headers = [
            'Date' => $expires, // 这个是 expires
        ];
        $sign = $this->genAccessSign('PUT', $object, $headers);
        $param = [];
        foreach ([
            'OSSAccessKeyId' => $this->accessKeyId,
            'Expires' => $expires,
            'Signature' => $sign,
        ] as $k => $v) {
            $param[] = $k .'=' . $v;
        };
        return $this->host . '/' . $object . '?' . implode('&', $param);
    }

    /**
     * 生成签名
     * @see https://help.aliyun.com/document_detail/31951.html
     * @param string $method
     * @param string $path
     * @param array $headers
     * @return string api签名
     */
    private function genAccessSign($method, $path, $headers)
    {
        // build CanonicalizedOSSHeaders
        $ossHeaders = [];
        ksort($headers);
        foreach ($headers as $key => $value) {
            if (substr($key, 0, 6) === 'x-oss-') {
                $ossHeaders[] = $key . ':' . $value;
            }
        }
        $ossHeaders = implode("\n", $ossHeaders);
        // build Authorization
        $params = [
            $method, // VERB
            '', // Content-MD5
            $headers['Content-Type'] ?? '', // Content-Type
            $headers['Date'], // Date
        ];
        if ($ossHeaders) {
            $params[] = $ossHeaders; // CanonicalizedOSSHeaders
        }
        // append CanonicalizedResource
        $params[] = $this->getFullObjectPath($path); // CanonicalizedResource
        $str = implode("\n", $params);
        return base64_encode(hash_hmac('sha1', $str, $this->accessKeySecret, true));
    }

    /**
     * 发送请求
     * @param string $method
     * @param string $api
     * @param array $headers
     * @param mixed $body
     * @return array
     */
    private function sendRequest($method, $api, $headers, $body = null)
    {
        $code = 0;
        $status = false;
        $raw = false;
        try {
            $res = Muggle::guzzleHttpRequest($method, $api, $body, [ 'headers' => $headers, 'http_errors' => false ]);
            $code = $res->getStatusCode();
            // @fixme 2xx正常
            $raw = $res->getBody()->getContents();
            if ($code >= 300) {
                if (!empty($raw)) {
                    $xml = Html::xmlToArray($raw);
                    if ($xml) {
                        if (isset($xml['Message'])) {
                            return $this->setErrors($xml['Message']);
                        }
                    }
                } else {
                    return $this->setErrors('error: status code ' . $code);
                }
            } else {
                $status = true;
            }
        } catch (\Exception $e) {
            return $this->setErrors($e->getMessage());
        }
        return [
            'status' => $status,
            'code' => $code,
            'raw' => $raw
        ];
    }

    /**
     * 设置错误信息
     * @return false
     */
    private function setErrors($error = null)
    {
        if ($error) {
            $this->_error = $error;
            Yii::error('[storage] ' . $this->_error);
        } else {
            $this->_error = 'timeout or unknown network error.';
            Yii::error('[storage] timeout or unknown network error.');
        }
        return false;
    }

    /**
     * 获取 Authorization
     * @param string $method
     * @param string $path
     * @param array $headers
     * @return string
     */
    private function getAuthorization($method, $path, $headers)
    {
        return 'OSS ' . $this->accessKeyId . ':' . $this->genAccessSign($method, $path, $headers);
    }

    /**
     * 获取 url object 拼装路径
     * @param string $path
     * @return string
     */
    private function getFullUrlPath($path)
    {
        if (substr($path, 0, 1) !== '/') {
            return 'https://' . $this->getEndPoint() . '/' . $path;
        } else {
            return 'https://' . $this->getEndPoint() . $path;
        }
    }

    /**
     * 获取 bucket object 拼装路径
     * @param string $path
     * @return string
     */
    private function getFullObjectPath($path)
    {
        if (substr($path, 0, 1) !== '/') {
            return '/' . $this->bucket . '/' . $path;
        } else {
            return '/' . $this->bucket . $path;
        }
    }
}
