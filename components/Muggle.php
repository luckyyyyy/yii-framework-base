<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\BaseObject;
use yii\base\Exception;
use yii\helpers\BaseHtml;
use yii\helpers\Json;
use yii\helpers\Url;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * 一些奇怪的方法合集
 * @author William Chan <root@williamchan.me>
 */
class Muggle extends BaseObject
{

    /**
     * 是否是debug环境
     * @return bool
     */
    public static function isDebugEnv()
    {
        return defined('MUGGLE_DEBUG_ENABLE') && MUGGLE_DEBUG_ENABLE;
    }

    /**
     * 用户浏览器是否为 iOS 系统
     * @return bool
     */
    public static function isIos()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/iP(?:ad|od|hone); /', $_SERVER['HTTP_USER_AGENT']);
    }

    /**
     * 用户浏览器是否为 Android 系统
     * @return bool
     */
    public static function isAndroid()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], '; Android ') !== false;
    }

    /**
     * 用户浏览器是否为移动设备
     * @return bool
     */
    public static function isMobile()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false;
    }

    /**
     * 是否在微信中打开
     * @return bool
     */
    public static function isWechat()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger/') !== false;
    }

    /**
     * 获得一个真随机数
     * @param int $min
     * @param int $max
     * @return int
     */
    public static function randomFromDev($min = 0, $max = 10000)
    {
        $rand = 0;
        $diff = $max - $min;
        if ($diff > PHP_INT_MAX) {
            throw new Exception('LIMIT PHP_INT_MAX');
        }
        try {
            $dev = fopen('/dev/urandom', 'r');
            stream_set_read_buffer($dev, PHP_INT_SIZE);
            $bytes = fread($dev, PHP_INT_SIZE);
            if ($bytes === false || strlen($bytes) != PHP_INT_SIZE) {
                // try random_int
                throw new Exception();
            }
            fclose($dev);
            if (PHP_INT_SIZE == 8) {
                list($higher, $lower) = array_values(unpack('N2', $bytes));
                $value = $higher << 32 | $lower;
            } else {
                list($value) = array_values(unpack('Nint', $bytes));
            }
            $val = $value & PHP_INT_MAX;
            $fp = (float) $val / PHP_INT_MAX;
            $rand = (int) round($fp * $diff) + $min;
        } catch (Exception $e) {
            $rand = random_int($min, $max);
        }
        return $rand;
    }

    /**
     * @param int $length
     * @param string $chars
     * @return string
     * @throws Exception
     */
    public static function random($length = 10, $chars = '0123456789')
    {
        $hash = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[static::randomFromDev(0, $max)];
        }
        return $hash;
    }

    /**
     * 网络请求之客户端IP
     * @return string
     */
    public static function clientIp()
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        } else if (php_sapi_name() === 'cli') {
            return php_uname('n');
        } else {
            return '';
        }
    }

    /**
     * 简易 HTTP 请求（即将废弃）
     * @param string $method 请求方法
     * @param string|array $url 目标网址
     * @param array|string $params 请求参数或内容体，其中 @ 开头的参数表示文件
     * @param array $headers 额外需要增加的 http 头
     * @param mixed &$responseHeader
     * @return string
     */
    public static function httpRequest($method, $url, $params = [], array $headers = [], &$responseHeader = null)
    {
        $url = Url::to($url);
        $http = ['timeout' => 3];
        $ssl = ['verify_peer' => false];
        $method = strtoupper($method);
        if (($pos = strpos($method, '_JSON')) !== false) {
            $headers['Content-Type'] = 'application/json';
            $http['method'] = substr($method, 0, $pos);
            $http['content'] = Json::encode($params);
        } else {
            $http['method'] = $method;
            if (is_string($params)) {
                $http['content'] = $params;
            } elseif ($method === 'POST') {
                $boundary = false;
                foreach ($params as $key => $value) {
                    if (substr($key, 0, 1) === '@') {
                        $boundary = md5(microtime(true));
                        break;
                    }
                }
                if ($boundary === false) {
                    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    $http['content'] = http_build_query($params);
                } else {
                    $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
                    $http['content'] = '';
                    foreach ($params as $key => $value) {
                        $http['content'] .= '--' . $boundary . "\r\n";
                        if (substr($key, 0, 1) === '@') {
                            if (is_string($value)) {
                                $value = ['name' => basename($value), 'content' => @file_get_contents($value)];
                            }
                            if (isset($value['name'], $value['content'])) {
                                $http['content'] .= 'Content-Disposition: form-data; name="' . substr($key, 1) . '"; filename="' . $value['name'] . "\"\r\n";
                                $http['content'] .= "\r\n" . $value['content'] . "\r\n";
                            }
                        } else {
                            $http['content'] .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n";
                            $http['content'] .= "\r\n" . $value . "\r\n";
                        }
                    }
                    $http['content'] .= '--' . $boundary . "--\r\n";
                }
            } elseif (count($params) > 0) {
                $url = $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
            }
        }
        if (isset($http['content']) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        if (isset($headers['ssl'])) {
            $ssl = array_merge($ssl, $headers['ssl']);
            unset($headers['ssl']);
        }
        if (count($headers) > 0) {
            $http['header'] = '';
            foreach ($headers as $key => $value) {
                $http['header'] .= $key . ': ' . $value . "\r\n";
            }
        }
        $raw = @file_get_contents($url, false, stream_context_create(['http' => $http, 'ssl' => $ssl]));
        if (isset($http_response_header)) {
            $responseHeader = $http_response_header;
        }
        return $raw;
    }

    /**
     * 使用 GuzzleHttp 进行 HTTP 请求
     * @param string $method 请求方法 支持 POST GET XXX_JSON
     * @param string|array $url 目标网址
     * @param array|string $params 请求参数或内容体，其中 @ 开头的参数表示文件
     * @param array $config 额外的配置
     * @return GuzzleHttp\Psr7\Response
     */
    public static function guzzleHttpRequest($method, $url, $params = [], array $config = [])
    {
        $url = Url::to($url);
        $http = [
            RequestOptions::TIMEOUT => 3
        ];
        $method = strtoupper($method);
        $headers = [];
        if (($pos = strpos($method, '_JSON')) !== false) {
            $headers['Content-Type'] = 'application/json';
            $http['method'] = substr($method, 0, $pos);
            // 从源码看 他的写法非常不严谨 使用了 json_encode 会让中文变为\xxx
            // $http[RequestOptions::JSON] = $params;
            $http[RequestOptions::BODY] = Json::encode($params);
        } else {
            $http['method'] = $method;
            if (is_string($params)) {
                if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                    $http[RequestOptions::BODY] = $params;
                } else {
                    $http[RequestOptions::QUERY] = $params;
                }
            } elseif ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                $boundary = false;
                foreach ($params as $key => $value) {
                    if (substr($key, 0, 1) === '@') {
                        $boundary = md5(microtime(true));
                        break;
                    }
                }
                if ($boundary === false) {
                    $headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    $http[RequestOptions::FORM_PARAMS] = $params;
                } else {
                    $headers['Content-Type'] = 'multipart/form-data;';
                    $http[RequestOptions::MULTIPART] = [];
                    foreach ($params as $key => $value) {
                        if (substr($key, 0, 1) === '@') {
                            if (is_string($value)) {
                                $http[RequestOptions::MULTIPART][] = [
                                    'name' => basename($value),
                                    'contents' => fopen($value, 'r'),
                                ];
                            } elseif (isset($value['name'], $value['content'])) {
                                $http[RequestOptions::MULTIPART][] = [
                                    'name' => substr($key, 1),
                                    'contents' => $value['content'],
                                    'filename' => $value['name'],
                                ];
                            }
                        } else {
                            $http[RequestOptions::MULTIPART][] = [
                                'name' => $key,
                                'contents' => $value,
                            ];
                        }
                    }
                }
            } elseif (count($params) > 0) {
                $url = $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
            }
        }
        if (isset($config[RequestOptions::HEADERS]) && is_array($config[RequestOptions::HEADERS])) {
            $config[RequestOptions::HEADERS] = array_merge($config[RequestOptions::HEADERS], $headers);
        } else {
            $config[RequestOptions::HEADERS] = $headers;
        }
        $basic = false;
        if (isset($config['basic']) && $config['basic'] === true) {
            $basic = true;
            unset($config['basic']);
        }
        $config = array_merge($http, $config);
        $client = new Client();
        $response = $client->request($http['method'], $url, $config);
        if ($basic === true) {
            return $response->getBody()->getContents();
        } else {
            return $response;
        }
    }

    /**
     * 下划线转驼峰
     * @param $str
     * @return string
     */
    public static function underlineToHump($str)
    {
        return preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
    }

    /**
     *  驼峰转下划线
     * @param $str
     * @return string
     */
    public static function humpToUnderline($str)
    {
        return preg_replace_callback('/([A-Z]{1})/', function ($matches) {
            return '_' . strtolower($matches[0]);
        }, $str);
    }

    /**
     * 生成树形结构
     * @param array $list
     * @param string $pid
     * @param string $child
     * @param int $root
     * @return array
     */
    public static function makeTree(array $list, $pid = 'parent_id', $child = 'children', $root = 0)
    {
        $tree = [];
        $packData = $list;
        foreach ($packData as $k => $v) {
            if ($v[$pid] == $root) {
                $tree[] = &$packData[$k];
            } else {
                $packData[$v[$pid]][$child][] = &$packData[$k];
            }
        }
        return $tree;
    }
}
