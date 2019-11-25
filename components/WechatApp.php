<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\Exception;
use yii\base\BaseObject;
use yii\base\InvalidParamException;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

/**
 * 微信公众平台（非开放平台）
 *
 * @property string $scenario 使用场景
 * @property-read string $appToken 公众平台的 access_token
 * @property-read string $jsTicket
 *
 * @method WechatReplier createReplier(array $config = [])
 * @method WechatPay WechatPay(array $config = [])
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatApp extends BaseObject
{
    use LogTrait;

    /**
     * 不同类型的应用未必支持所有的API
     * 具体看开放平台 https://open.weixin.qq.com/
     */
    const TYPE_MP = 'mp'; // 公众号
    const TYPE_WXAPP = 'wxapp'; // 小程序
    const TYPE_APP = 'app'; // APP
    const TYPE_WEB = 'web'; // 网站

    /**
     * @var array
     */
    public $scenarios = [];

    /**
     * @var string
     */
    public $cacheId = 'cache2';

    /**
     * @var string 当前场景的表前缀
     */
    public $prefix;

    /**
     * @var string 平台类型 (mp=公众号/web=网页/wxapp=小程序/app=APP)
     */
    public $type = 'mp';

    /**
     * @var string 公众平台应用ID
     */
    public $appId;

    /**
     * @var string 公众平台应用密钥
     */
    public $appSecret;

    /**
     * @var string 接口令牌
     */
    public $token;

    /**
     * @var string 消息加解密密钥
     */
    public $encodingAesKey;

    /**
     * 模板消息别名列表
     * @var array
     */
    public $templates = [];

    /**
     * @var string API 基址
     */
    public $apiBaseUrl = 'https://api.weixin.qq.com/cgi-bin/';

    /**
     * @var string 第三方授权地址
     */
    public $authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * @var string 第三方接口基址
     */
    public $snsBaseUrl = 'https://api.weixin.qq.com/sns/';

    /**
     * @var string 统计数据接口
     */
    public $datacubeBaseUrl = 'https://api.weixin.qq.com/datacube/';

    /**
     * @var string 小程序部分接口
     */
    public $wxaBaseUrl = 'https://api.weixin.qq.com/wxa/';

    /**
     * @var string 商户ID
     */
    public $mchId;

    /**
     * @var string 支付秘钥
     */
    public $partnerKey;

    /**
     * @var string ssl key path
     */
    public $sslKey;

    /**
     * @var string ssl cert path
     */
    public $sslCert;

    private $_appToken;
    private $_jsTicket;
    private $_scenario;
    private $_tokenExpire = 0;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->_scenario === null) {
            $this->setScenario(key($this->scenarios));
        }
    }

    /**
     * 获取全部场景
     * @return array
     */
    public function getScenarios($scenario = null)
    {
        if ($scenario && $this->scenarios[$scenario]) {
            return $this->scenarios[$scenario];
        } else {
            return $this->scenarios;
        }
    }

    /**
     * 获取使用场景
     * @return string
     */
    public function getScenario()
    {
        return $this->_scenario;
    }

    /**
     * 切换使用场景
     * @param string $scenario 公众号的scenario
     * @param bool $tablePrefix 是否一起切换tablePrefix
     * @return WechatApp
     * @throws NotFoundHttpException
     */
    public function setScenario($scenario, $tablePrefix = true)
    {
        $config = $this->scenarios[$scenario] ?? null;
        if ($config) {
            $this->_scenario = $scenario;
            $this->_appToken = null;
            $this->_jsTicket = null;
            $this->_tokenExpire = 0;
            if ($tablePrefix) {
                Yii::$app->db->tablePrefix = $config['prefix'] . '_';
                if (Yii::$app->has('user')) {
                    Yii::$app->user->setUser(null);
                }
            }
            Yii::configure($this, $config);
            return $this;
        }
        throw new NotFoundHttpException('scenario not found');
    }

    /**
     * 是不是公众号场景
     * @return bool
     */
    public function getIsMp()
    {
        return $this->type === self::TYPE_MP;
    }

    /**
     * 是不是网页（电脑端）
     * @return bool
     */
    public function getIsWeb()
    {
        return $this->type === self::TYPE_WEB;
    }

    /**
     * 是不是微信小程序场景
     * @return bool
     */
    public function getIsWxapp()
    {
        return $this->type === self::TYPE_WXAPP;
    }

    /**
     * 获取公众平台的 access token
     * @param bool $refresh 是否强制刷新
     * @return string|bool 成功返回公众号 access_token，失败时返回 false
     */
    public function getAppToken($refresh = false)
    {
        $now = time();
        if ($this->_appToken !== null && !$refresh && ($now > $this->_tokenExpire)) {
            return $this->_appToken;
        }
        // fetch from cache
        $cacheKey = 'wechat.appToken.' . $this->appId;
        $cache = Yii::$app->get($this->cacheId);
        $data = $cache->get($cacheKey);
        if ($data !== false && isset($data['access_token']) && $data['access_token'] !== $this->_appToken) {
            $this->_tokenExpire = $data['time'] + $cache['expires_in'];
            return $this->_appToken = $data['access_token'];
        }
        // fetch from remote server
        $params = ['grant_type' => 'client_credential', 'appid' => $this->appId, 'secret' => $this->appSecret];
        $data = $this->api('token', $params);
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
     * 获取公众平台的 jsapi_ticket
     * @param bool $refresh 是否强制刷新
     * @return string|bool 成功返回 jsapi_ticket，失败返回 false
     */
    public function getJsTicket($refresh = false)
    {
        if ($this->_jsTicket !== null && !$refresh) {
            return $this->_jsTicket;
        }
        // fetch from cache
        $cacheKey = 'wechat.jsTicket.' . $this->appId;
        $cache = Yii::$app->get($this->cacheId);
        if ($refresh !== true) {
            $data = $cache->get($cacheKey);
            if ($data !== false && isset($data['ticket']) && $data['ticket'] !== $this->_jsTicket) {
                return $this->_jsTicket = $data['ticket'];
            }
        }
        // fetch from remote server
        $data = $this->api('ticket/getticket', ['type' => 'jsapi']);
        if (isset($data['ticket'])) {
            unset($data['errcode'], $data['errmsg']);
            $data['expires_in'] -= 60;
            $cache->set($cacheKey, $data, $data['expires_in']);
            return $this->_jsTicket = $data['ticket'];
        }
        return false;
    }

    /**
     * 生成 JSSDK 配置内容
     * @param string $url 签名网址
     * @param array $config 自定义配置
     * @return array
     */
    public function getJsConfig($url, $config = [])
    {
        $config = array_merge([
            'debug' => false,
            'appId' => $this->appId,
            'timestamp' => time(),
            'nonceStr' => uniqid('wjs_'),
            'signature' => '',
        ], $config);
        if (!isset($config['jsApiList'])) {
            $config['jsApiList'] = [
                'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ', 'onMenuShareWeibo',
                'startRecord', 'stopRecord', 'onVoiceRecordEnd',
                'playVoice', 'pauseVoice', 'stopVoice', 'onVoicePlayEnd',
                'uploadVoice', 'downloadVoice',
                'chooseImage', 'previewImage', 'uploadImage', 'downloadImage',
                'translateVoice', 'getNetworkType',
                'openLocation', 'getLocation',
                'hideOptionMenu', 'showOptionMenu',
                'hideMenuItems', 'showMenuItems',
                'hideAllNonBaseMenuItem', 'showAllNonBaseMenuItem',
                'closeWindow',
                'scanQRCode',
                'chooseWXPay',
                'openProductSpecificView',
                'addCard', 'chooseCard', 'openCard',
            ];
        }
        // signature
        $sParams = [
            'noncestr' => $config['nonceStr'],
            'jsapi_ticket' => $this->getJsTicket(),
            'timestamp' => $config['timestamp'],
            'url' => $url,
        ];
        if (($pos = strpos($sParams['url'], '#')) !== false) {
            $sParams['url'] = substr($sParams['url'], 0, $pos);
        }
        ksort($sParams);
        $rawData = '';
        foreach ($sParams as $k => $v) {
            $rawData .= '&' . $k . '=' . $v;
        }
        $config['signature'] = sha1(substr($rawData, 1));
        return $config;
    }

    /**
     * 调用公众平台接口
     * @param string $api 接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求接口
     */
    public function api($api, array $params = [], $method = 'GET')
    {
        $url = $this->apiBaseUrl . $api;
        if ($api !== 'token') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($this->getAppToken());
        }
        return $this->apiRequest($url, $params, $method);
    }

    /**
     * 统计数据接口
     * @param string $api 接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求接口
     */
    public function datacubeApi($api, array $params = [], $method = 'POST_JSON')
    {
        $url = $this->datacubeBaseUrl . $api;
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($this->getAppToken());
        return $this->apiRequest($url, $params, $method);
    }

    /**
     * 小程序接口
     * @param string $api 接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求接口
     */
    public function wxaApi($api, array $params = [], $method = 'POST_JSON')
    {
        $url = $this->wxaBaseUrl . $api;
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'access_token=' . urlencode($this->getAppToken());
        return $this->apiRequest($url, $params, $method);
    }

    /**
     * 调用第三方授权接口
     * @param string $api 接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求结果
     */
    public function snsApi($api, array $params = [], $method = 'GET')
    {
        $url = $this->snsBaseUrl . $api;
        return $this->apiRequest($url, $params, $method, false);
    }

    /**
     * 微信平台接口调用
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
        $raw = $response->getBody()->getContents();
        try {
            $data = Json::decode($raw);
            if (isset($data['errcode']) && $data['errcode'] !== 0) {
                if (($data['errcode'] == 40001 || $data['errcode'] == 42001 || strstr($data['errmsg'], 'access_token')) && $renew === true) {
                    $token = $this->getAppToken(true);
                    $url = preg_replace('/access_token=[^&]+/', 'access_token=' . urlencode($token), $url);
                    return $this->apiRequest($url, $params, $method);
                }
                $errors = 'Wechat API Error: #' . $data['errcode'] . ' - ' . $data['errmsg'];
                $this->log($method . ' ' . $url . "\n" . $errors . "\n" . var_export($params, true));
                throw new Exception($errors);
            }
            return $data;
        } catch (InvalidParamException $e) {
            return $raw;
        }
    }

    /**
     * 创建消息自动回复对象
     * @param array $config 额外的配置信息
     * @return WechatReplier
     */
    public function createReplier(array $config = [])
    {
        return new WechatReplier(array_merge([
            'appId' => $this->appId,
            'token' => $this->token,
            'encodingAesKey' => $this->encodingAesKey,
        ], $config));
    }

    /**
     * 创建微信支付对象
     * @param array $config 额外的配置信息
     * @return WechatPay
     * @throws NotFoundHttpException
     */
    public function createWechatPay(array $config = [])
    {
        return new WechatPay(array_merge([
            'appId' => $this->appId,
            'token' => $this->token,
            'mchId' => $this->mchId,
            'partnerKey' => $this->partnerKey,
            'sslCert' => $this->sslCert,
            'sslKey' => $this->sslKey,
        ], $config));
    }

    /**
     * 获取第三方授权登录地址
     * @param string $redirect 默认为当前网址
     * @param array $params 请求参数覆盖
     * @return string
     */
    public function fetchAuthUrl($redirect = null, array $params = [])
    {
        if ($redirect === null) {
            $redirect = Yii::$app->request->getAbsoluteUrl();
        }
        $authUrl = $this->authUrl;
        if ($this->isWeb) {
            $authUrl = substr($authUrl, 0, strpos($authUrl, '/', 11)) . '/connect/qrconnect';
            $scope = 'snsapi_login';
        } else {
            $scope = 'snsapi_userinfo';
        }
        $params = array_merge([
            'appid' => $this->appId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $scope,
            'state' => '',
        ], $params);
        return $authUrl . '?' . http_build_query($params) . '#wechat_redirect';
    }

    /**
     * 换取 AccessToken
     * @param string $code 授权码
     * @param array $params 请求参数覆盖
     * @return array
     */
    public function fetchAccessToken($code, array $params = [])
    {
        $params = array_merge([
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ], $params);
        return $this->snsApi('oauth2/access_token', $params);
    }

    /**
     * 发送客服消息 48小时内用户
     * msgType: text, image, voice, video, music, news, mpnews, wxcard
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140547
     * @param string $openid
     * @param mixed $data
     * @param string $msgType
     * @param bool $thorw
     * @throws Exception
     */
    public function sendCustomer($openid, $data, $msgType = 'news', $thorw = false)
    {
        $params = [
            'touser' => $openid,
            'msgtype' => $msgType,
            $msgType => $data,
        ];
        try {
            $this->api('message/custom/send', $params, 'POST_JSON');
        } catch (\Exception $e) {
            Yii::warning($e->getMessage());
            if ($thorw) {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * 发送模板消息
     * @param string $openid 目标用户 openid
     * @param string $template 模板 id
     * @param string $url 消息网址
     * @param array $data
     * @param bool $thorw
     * @throws Exception
     */
    public function sendTemplate($openid, $url, $template, array $data = [], $thorw = false)
    {
        if (isset($this->templates[$template])) {
            $template = $this->templates[$template];
        }
        $params = ['touser' => $openid, 'template_id' => $template];
        if ($this->type === self::TYPE_WXAPP) {
            $api = 'message/wxopen/template/send';
            $params['page'] = $url;
        } else {
            $api = 'message/template/send';
            if (is_array($url) && isset($url['appid'], $url['pagepath'])) {
                $params['miniprogram'] = [
                    'appid' => $url['appid'],
                    'pagepath' => $url['pagepath'],
                ];
                if (isset($url['url'])) {
                    $params['url'] = $url['url'];
                }
            } elseif (is_string($url)) {
                $params['url'] = $url;
            }
        }
        foreach ($data as $i => $value) {
            if ($this->type === self::TYPE_WXAPP && in_array($i, ['form_id', 'color', 'emphasis_keyword'])) {
                $params[$i] = $value;
                unset($data[$i]);
            } elseif (!is_array($value)) {
                $data[$i] = ['value' => $value, 'color' => '#173177'];
            }
        }
        $params['data'] = $data;
        try {
            $this->api($api, $params, 'POST_JSON');
        } catch (\Exception $e) {
            Yii::warning($e->getMessage());
            if ($thorw) {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * 获取关注后的用户详细信息
     * @param string $openid 目标用户 openid
     * @return array
     */
    public function fetchUserInfo(string $openid)
    {
        return $this->api('user/info', [
            'openid' => $openid,
            'lang' => 'zh_CN',
        ]);
    }

    /**
     * 换取 Session
     * @param string $code 授权码
     * @param array $params 请求参数覆盖
     * @return array
     */
    public function fetchSession($code, array $params = [])
    {
        $params = array_merge([
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ], $params);
        return $this->snsApi('jscode2session', $params);
    }


    /**
     * 解密小程序敏感数据
     * @param string $data 加密后的数据
     * @param string $iv base64 encoded initilize_vertor
     * @param string $key base64 encoded session_key
     * @return array
     */
    public function decryptAesData($data, $iv, $key)
    {
        $key = base64_decode($key);
        $iv = base64_decode($iv);
        $data = openssl_decrypt(base64_decode($data), 'AES-128-CBC', $key, 1, $iv);
        return Json::decode($data);
    }

    /**
     * 获取小程序二维码（已经对相同参数做了缓存）
     * @param string $path 小程序页面路径
     * @param bool|string $scene 场景 (不填生成永久二维码)
     * @param array $style 样式
     * @param bool $qrcode 是否为普通二维码
     * @return mixed
     * @see https://developers.weixin.qq.com/miniprogram/dev/api/open-api/qr-code/createWXAQRCode.html
     */
    public function fetchWxaCode($path, $scene = false, $style = [], $qrcode = false)
    {
        $unique = md5(Json::encode(array_merge(['path' => $path, 'scene' => $scene, 'qrcode' => $qrcode], $style)));
        $cache = Yii::$app->get($this->cacheId);
        $key = '@WECHAT_WXAPP_QRCODE_' . $this->scenario . '_' . $unique;
        $raw = $cache->get($key);
        if (!$raw) {
            $params = [
                'path' => $path,
                'auto_color' => true,
            ];
            if (isset($style['width'])) {
                $params['width'] = $style['width'];
            }
            if (!$qrcode) {
                if (isset($style['auto_color'])) {
                    $params['auto_color'] = $style['auto_color'];
                }
                if (isset($style['line_color'])) {
                    $params['line_color'] = $style['line_color'];
                }
                if (isset($style['is_hyaline'])) {
                    $params['is_hyaline'] = $style['is_hyaline'];
                }
                if ($scene) {
                    $params['scene'] = $scene;
                    $raw = $this->wxaApi('getwxacodeunlimit', $params);
                } else {
                    $raw = $this->wxaApi('getWXACode', $params);
                }
            } else {
                $raw = $this->api('wxaapp/createwxaqrcode', $params);
            }
            $cache->set($key, $raw, 86400);
        }
        return $raw;
    }

    /**
     * 上传媒体文件
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1444738726
     * @param string $type 文件类型，支持：image, voice, video, thumb
     * @param string $file 文件路径或名称
     * @param string $content 文件内容
     * @return string 媒体文件 ID，若失败则返回 null
     */
    public function uploadMedia($type, $file, $content = null)
    {
        $cache = Yii::$app->get($this->cacheId);
        $key = '@WECHAT_MEIDA_' . $this->scenario . '_' . $type . '_' . $file;
        $media = $cache->get($key);
        if ($media) {
            return $media;
        } else {
            if ($content === null) {
                $content = Muggle::guzzleHttpRequest('GET', $file, [], ['basic' => true]);
            }
            $data = $this->api('media/upload?type=' . $type, [
                '@media' => ['name' => basename($file), 'content' => $content],
            ], 'POST');
            if (isset($data['media_id'])) {
                $cache->set($key, $data['media_id'], 86400 * 2);
                return $data['media_id'];
            } else {
                return null;
            }
        }
    }

    /**
     * 下载多媒体文件
     * @param string $media_id
     * @param string $path 保存位置，传入 null 返回网址
     * @param bool $jssdk 是否高清（只针对语音）
     * @return bool
     */
    public function fetchMediaFile($media_id, $path = null, $jssdk = false)
    {
        $api = 'media/get';
        if ($jssdk === true) {
            $api .= '/jssdk';
        }
        $params = [
            'media_id' => $media_id,
        ];
        if ($path === null) {
            $params['access_token'] = $this->getAppToken();
            return $this->apiBaseUrl . $api . '?' . http_build_query($params);
        }
        $raw = $this->api($api, $params);
        $storage = Yii::$app->storage;
        return $storage->putObject($path, $raw);
    }
}
