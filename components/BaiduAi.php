<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\Exception;
use yii\base\BaseObject;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

/**
 * 百度AI平台
 *
 * App ID: 2637679
 * API Key: a3R86ZRHefLImXsVxjXHkAZ7
 * Secret Key: ueyLeDeCdxYVflor39Mf1PGtemgQOy6q
 *
 * @see http://ai.baidu.com/docs
 * @property-read string $openAppToken 百度AI的 access_token
 *
 * @author William Chan <root@williamchan.me>
 */
class BaiduAi extends BaseObject
{
    /**
     * @var string APP Key
     */
    public $appId = '';

    /**
     * @var string APP Key
     */
    public $appKey = '';

    /**
     * @var string APP Secret
     */
    public $appSecret = '';

    /**
     * @var string
     */
    public $cacheId = 'cache2';

    private $_appToken;
    private $_tokenExpire;

    /**
     * 获取百度Ai的 access token
     * @param bool $refresh 是否强制刷新
     * @return string|bool 返回 access_token，失败时返回 false
     */
    public function getOpenAppToken($refresh = false)
    {
        $now = time();
        if ($this->_appToken !== null && !$refresh && ($now > $this->_tokenExpire)) {
            return $this->_appToken;
        }
        // fetch from cache
        $cacheKey = 'baidu.ai.open.appToken.' . $this->appId;
        $cache = Yii::$app->get($this->cacheId);
        $data = $cache->get($cacheKey);
        if ($data !== false && isset($data['access_token']) && $data['access_token'] !== $this->_appToken) {
            $this->_tokenExpire = $data['time'] + $cache['expires_in'];
            return $this->_appToken = $data['access_token'];
        }
        // fetch from remote server
        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->appKey,
            'client_secret' => $this->appSecret,
        ];
        $data = $this->apiRequest('https://openapi.baidu.com/oauth/2.0/token', $params, 'POST');
        $data = Json::decode($data);
        if (isset($data['access_token'])) {
            $data['expires_in'] -= 60;
            $data['time'] = $now;
            $cache->set($cacheKey, $data, $data['expires_in']);
            $this->_tokenExpire = $now + $data['expires_in'];
            return $this->_appToken = $data['access_token'];
        }
        return false;
    }

    /**
     * 调用百度接口
     * @param string $url 接口地址
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求接口
     */
    public function openApi($url, array $params = [], $method = 'GET')
    {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'tok=' . urlencode($this->getOpenAppToken());
        return $this->apiRequest($url, $params, $method);
    }

    /**
     * 接口调用
     * @param string $url 接口完整地址
     * @param string $method 接口请求方式，默认为 GET，还可支持 POST & POST_JSON
     * @param array $params 请求参数
     * @param bool $renew 是否自动换取新 token
     * @return array 请求结果
     * @throws \yii\base\Exception
     */
    public function apiRequest($url, array $params = [], $method = 'GET', $renew = true)
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                $response = Muggle::guzzleHttpRequest($method, $url, $params);
                break;
            } catch (\Exception $e) {
                $this->log($method . ' ' . $url . " - try count $i - " . $e->getMessage());
                if ($i === 2) {
                    throw new Exception('Wechat API Error: http proxy error.');
                }
            }
        }
        // TODO 百度这接口。。。 待验证
        return $response->getBody()->getContents();
    }

    /**
     * 百度语音合成接口
     * @see http://ai.baidu.com/docs#/TTS-API/top
     * @param string $text 合成的文本，使用UTF-8编码。小于512个中文字或者英文数字。（文本在百度服务器内转换为GBK后，长度必须小于1024字节）
     * @param bool $raw 是否返回raw
     * @param string $cuid 用户唯一标识，用来区分用户，计算UV值。建议填写能区分用户的机器 MAC 地址或 IMEI 码，长度为60字符以内
     * @param int $spd 语速，取值0-9，默认为5中语速
     * @param int $pit 音调，取值0-9，默认为5中语调
     * @param int $vol 音量，取值0-15，默认为5中音量
     * @param int $per 发音人选择, 0为普通女声，1为普通男生，3为情感合成-度逍遥，4为情感合成-度丫丫，默认1
     * @return void
     */
    public function text2Audio($text, $raw = true, $cuid = '0', $spd = 5, $pit = 5, $vol = 5, $per = 1)
    {
        static $host = 'https://tsn.baidu.com/text2audio';
        $params = [
            'tex' => $text,
            'cuid' => $cuid,
            'ctp' => 1,
            'lan' => 'zh',
            'spd' => $spd,
            'pit' => $pit,
            'vol' => $vol,
            'per' => $per,
        ];
        if ($raw) {
            return $this->openApi($host, $params);
        } else {
            $params['tok'] = urlencode($this->getOpenAppToken());
            return $host . '?' . http_build_query($params);
        }
    }
}
