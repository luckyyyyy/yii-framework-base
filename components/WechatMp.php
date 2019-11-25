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
 * 微信后台爬虫
 * @link https://mp.weixin.qq.com
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatMp extends BaseObject
{
    use LogTrait;

    public $cache = 'cache2';
    public $username;
    public $password;
    public $scenarios = [];

    public $apiBaseUrl = 'https://mp.weixin.qq.com/cgi-bin';
    public $apiBaseUrl2 = 'https://mp.weixin.qq.com/misc';

    private $_scenario;
    private $_referer = 'https://mp.weixin.qq.com/';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->cache !== false) {
            $this->cache = Yii::$app->get($this->cache);
        }
        if ($this->_scenario === null) {
            $this->setScenario(key($this->scenarios));
        }
    }

    /**
     * 接口调用
     * @param string $url 接口完整地址
     * @param array $params 请求参数
     * @param string $method 接口请求方式，默认为 GET，还可支持 POST & POST_JSON
     * @return GuzzleHttp\Psr7\Response
     * @throws \yii\base\Exception
     */
    public function apiRequest($api, array $params = [], array $config = [], $method = 'GET')
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                $res = Muggle::guzzleHttpRequest($method, $api, $params, $config);
                break;
            } catch (\Exception $e) {
                $this->log($method . ' ' . $url . " - try count $i - " . $e->getMessage());
                if ($i === 2) {
                    throw new Exception('WechatMp API Error: http proxy error.');
                }
            }
        }
        return $res;
    }

    /**
     * 设置使用场景
     * @return string
     */
    public function setScenario($scenario)
    {
        $config = $this->scenarios[$scenario] ?? null;
        if ($config) {
            Yii::configure($this, $config);
            $this->_scenario = $scenario;
            return $this;
        }
        throw new NotFoundHttpException('scenario not found');
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
     * 清空授权
     * @return bool
     */
    public function clearVerifyCookie()
    {
        $key = 'WECHAT_MP_VERIFY_COOKIE_' . $this->_scenario;
        $key2 = 'WECHAT_MP_VERIFY_IMAGE_' . $this->_scenario;
        $this->cache->delete($key);
        $this->cache->delete($key2);
        return true;
    }

    /**
     * 获取验证阶段的cookie
     * @return string
     */
    public function getVerifyCookie()
    {
        $key = 'WECHAT_MP_VERIFY_COOKIE_' . $this->_scenario;
        $cookie = $this->cache->get($key);
        if (!$cookie) {
            $res = Muggle::guzzleHttpRequest('POST', $this->apiBaseUrl . '/bizlogin?action=startlogin', [
                'username' => $this->username,
                'pwd' => $this->password,
            ], [
                'headers' => [
                    'cookie' => $cookie,
                    'Referer' => $this->_referer
                ],
            ]);
            $data = Json::decode($res->getBody()->getContents());
            if (isset($data['redirect_url'])) {
                $cookie = implode(';', $res->getHeader('Set-Cookie'));
                $this->cache->set($key, $cookie, 10 * 60);
            } else {
                throw new Exception('wrong username or password.');
            }
        }
        return $cookie;
    }

    /**
     * 获取图片
     * @return string
     */
    public function getVerifyImage()
    {
        $key = 'WECHAT_MP_VERIFY_IMAGE_' . $this->_scenario;
        $image = $this->cache->get($key);
        if (!$image) {
            $cookie = $this->getVerifyCookie();
            $res = Muggle::guzzleHttpRequest('GET', $this->apiBaseUrl . '/loginqrcode', [
                'action' => 'getqrcode',
                'param' => '4300',
                'rd' => rand(1, 1000),
            ], [
                'headers' => [
                    'cookie' => $cookie,
                    'Referer' => $this->_referer
                ],
            ]);
            $image = $res->getBody()->getContents();
            if (!$image) {
                $this->clearVerifyCookie();
                return $this->getVerifyImage();
            }
        }
        return $image;
    }

    /**
     * 检查扫码情况
     * @return int
     */
    public function getVerifyAsk()
    {
        $cookie = $this->getVerifyCookie();
        $res = Muggle::guzzleHttpRequest('GET', $this->apiBaseUrl . '/loginqrcode', [
            'action' => 'ask',
        ], [
            'basic' => true,
            'headers' => [
                'cookie' => $cookie,
                'Referer' => $this->_referer
            ],
        ]);
        $data = Json::decode($res);
        return $data['status'] ?? -1;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function checkLogin()
    {
        if (($ask = $this->getVerifyAsk()) === 1) {
            $cookie = $this->getVerifyCookie();
            $res = Muggle::guzzleHttpRequest('POST', $this->apiBaseUrl . '/bizlogin?action=login', [], [
                'headers' => [
                    'cookie' => $cookie,
                    'Referer' => $this->_referer
                ],
            ]);
            $data = Json::decode($res->getBody()->getContents());
            if (isset($data['redirect_url'])) {
                parse_str($data['redirect_url'], $ret);
                if (isset($ret['token'])) {
                    $cookie = implode(';', $res->getHeader('Set-Cookie'));
                    return $this->setToken($cookie, $ret['token']);
                }
            } else {
                return $data;
            }
        } else {
            return $ask;
        }
    }

    /**
     * 设置token
     * @param string $cookie
     * @param string $token
     * @return void
     */
    public function setToken($cookie, $token)
    {
        $key = 'WECHAT_MP_TOKEN_' . $this->_scenario;
        $this->cache->set($key, [
            'cookie' => $cookie,
            'token' => $token,
        ]);
        return true;
    }

    /**
     * 获取token
     * @return array
     */
    public function getToken()
    {
        $key = 'WECHAT_MP_TOKEN_' . $this->_scenario;
        $token = $this->cache->get($key);
        if (!$token) {
            throw new Exception('authorization failure');
        }
        return $token;
    }

    /**
     * 获取文章列表
     * @param int $begin
     * @param int $count
     * @return mixed
     * @throws Exception
     */
    public function getAppmsg($begin = 0, $count = 2)
    {
        $token = $this->token;
        $res = $this->apiRequest($this->apiBaseUrl . '/newmasssendpage', [
            'token' => $token['token'],
            'count' => $count,
            'begin' => $begin,
        ], [
            'headers' => [
                'cookie' => $token['cookie'],
                'Referer' => $this->_referer
            ],
        ]);
        $data = Json::decode($res->getBody()->getContents());
        if (!$data || !isset($data['sent_list'])) {
            $this->clearVerifyCookie();
            throw new Exception();
        }
        return $data;
    }

    /**
     * 获取评论列表
     * @param int $begin
     * @param int $count
     * @param int $filter
     * @return mixed
     * @throws Exception
     */
    public function getAppmsgComment($comment_id, $begin = 0, $count = 20, $filter = [])
    {
        $token = $this->token;
        $res = $this->apiRequest($this->apiBaseUrl2 . '/appmsgcomment', [
            'action' => 'list_comment',
            'comment_id' => $comment_id,
            'token' => $token['token'],
            'count' => $count,
            'begin' => $begin,
            'day' => $filter['day'] ?? 0,
            'filtertype' => $filter['filtertype'] ?? 0,
            'type' => $filter['type'] ?? 2,
            'max_id' => 0, // 不知道什么参数
            'f' => 'json',
        ], [
            'headers' => [
                'cookie' => $token['cookie'],
                'Referer' => $this->_referer
            ],
        ]);
        $data = Json::decode($res->getBody()->getContents());
        if (!$data || !isset($data['comment_list'])) {
            $this->clearVerifyCookie();
            throw new Exception('授权过期，需要重新验证。');
        }
        return $data;
    }
}
