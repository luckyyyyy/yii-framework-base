<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\components;

use app\models\Identity;
use app\models\wechat\User as WechatUser;
use app\modules\user\PermChecker;
use Yii;
use yii\base\Exception;
use yii\base\Application;
use yii\base\InvalidCallException;
use yii\base\InvalidParamException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;
use yii\helpers\Url;

/**
 * User 扩展
 *
 * @property-read Identity $identity
 * @property-read WechatUser $user
 * @property-read bool $isRoot 是否为root
 * @property-read bool $isSuper 是否为超级管理
 * @property-read int $referral 当前用户受谁的推介
 * @property-read int $mineReferral 当前用户的36进制id
 *
 * @author William Chan <root@williamchan.me>
 */
class User extends \yii\web\User
{
    /**
     * @var bool 自动 Bearer 认证
     */
    public $enableAutoBearer = true;

    /**
     * @var array|string 用户登记注册地址
     */
    public $registerUrl;

    private $_touchIdentity;

    /**
     * 获取当前是属于谁的用户推介 __referral
     * @return int
     */
    public function getReferral()
    {
        // 如果是 Bearer Auth，不会带有session，因此不要主动启用。
        // 考虑以后把状态存到token的 state 上
        // if ($this->enableSession) {
        if (isset($_GET['__from_uid'])) {
            $referral = (int)base_convert(Yii::$app->session->get('__referral'), 36, 10);
            if ($referral !== $this->id) {
                return $referral;
            }
        }
        return 0;
    }

    /**
     * 获取当前用户id的36进制
     *
     * @return int
     */
    public function getMineReferral()
    {
        return base_convert($this->id, 10, 36);
    }

    /**
     * 增加角色判定
     * 简易扩展，不影响 yii rbac
     * # -> root, * -> super admin,
     * % -> module admin, @ -> module user
     * @inheritdoc
     */
    public function can($permissionName, $params = [], $allowCaching = true)
    {
        if ($permissionName === '#') { // root最高权限
            return $this->getIsRoot();
        } elseif ($permissionName === '*') { // 超级管理员 比root低一级别
            return $this->getIsSuper();
        } else {
            if (is_int($permissionName)) {
                return $this->isAdmin($permissionName);
            } else {
                $prefix = substr($permissionName, 0, 1);
                $module = strlen($permissionName) > 1 ? substr($permissionName, 1) : null;
                if ($prefix === '%') {
                    return $this->isAdmin($module);
                } elseif ($prefix === '@') {
                    return !$this->isGuest;  // 暂时是用户是否已注册（一般情况下用不到，后期会改造，比如是否已经是社区用户，是否已经是电商用户）
                }
            }
        }
        return parent::can($permissionName, $params, $allowCaching);
    }

    /**
     * @inheritdoc
     * @return \app\models\Identity
     */
    public function getIdentity($autoRenew = true)
    {
        $identity = parent::getIdentity($autoRenew);
        // 仅在 handleRequest 后有效，避开部分不规范用法
        if ($this->_touchIdentity === null && Yii::$app->requestedRoute !== null) {
            $this->_touchIdentity = true;
            // @fixme save referral (may also cause session in API requests)
            $request = Yii::$app->getRequest();
            $referral = $request->get('__from_uid');
            if ($referral !== null && $this->enableSession) {
                Yii::$app->session->set('__referral', $_GET['__from_uid']);
            }
            // auto bearer
            if ($identity === null && $this->enableAutoBearer) {
                $authHeader = $request->getHeaders()->get('Authorization');
                if ($authHeader !== null && preg_match('/^Bearer\s+(.*?)$/', $authHeader, $matches)) {
                    // $this->enableSession = false;
                    $identity = $this->loginByAccessToken($matches[1]);
                }
            }
        }
        return $identity;
    }

    /**
     * @inheritdoc
     */
    public function setIdentity($identity)
    {
        parent::setIdentity($identity);
        // check blocked
        // 全局封禁用户执行任意操作 封爬虫
        if ($identity !== null) {
            $duration = $identity->getBlockDuration();
            if ($duration > 0) {
                if ($duration > 86400 * 30) {
                    throw new ForbiddenHttpException('You are blocked.');
                } else {
                    throw new ForbiddenHttpException('You are blocked, ' . Html::humanDuration($duration) . ' left.');
                }
            } elseif ($duration === -1) {
                throw new HttpException(444);
            }
        }
    }


    /**
     * 获取关联的微信用户
     * @return WechatUser
     */
    public function getUser()
    {
        if (!$this->identity) {
            return null;
        }
        $identity = $this->getIdentity();
        if ($identity && $identity->user) {
            return $identity->user;
        } else {
            return null;
        }
    }

    /**
     * 设置关联的微信用户
     * 注意：此项慎用，除非你知道自己在做什么。
     * @param \app\models\wechat\User $user
     */
    public function setUser($user)
    {
        $identity = $this->getIdentity();
        if ($user === null) {
            unset($identity->user); // 更改微信用户
        } else {
            $identity->populateRelation('user', $user);
        }
        // forced to clear access caching
        parent::setIdentity($identity);
    }

    /**
     * @return bool 是否为根用户
     */
    public function getIsRoot()
    {
        return $this->identity && $this->identity->isRoot;
    }

    /**
     * @return bool 是否为超级管理
     */
    public function getIsSuper()
    {
        return $this->identity && $this->identity->canAdmin('*');
    }

    /**
     * 是否为管理员
     * @param string $module 管理模块，缺省为当前模块
     * @return bool
     */
    public function isAdmin($module = null)
    {
        if ($module === null) {
            $controller = Yii::$app->controller;
            if ($controller === null || $controller->module instanceof Application) {
                throw new InvalidCallException('Unable detect admin module: invalid controller.');
            }
            $module = $controller->module->uniqueId;
            if (substr($module, 0, 4) === 'api/') {
                $module = substr($module, 4);
            }
        }
        return $this->identity && $this->identity->canAdmin($module);
    }

    /**
     * 给当前用户 发送微信通知（客服消息）
     * @param int|Identity|WechatUser|string $id 需要通知的用户，写0等于当前用户自己。
     * @param string $scenario 发送消息的场景
     * @param mixed $data
     * @param string $type 消息类型
     * @param string $queue 使用队列
     * @return bool 是否通过初审（用队列的话只验证是否被添加到队列中，不保证一定成功）
     */
    public function sendCustomer($id, $scenario, $data = [], $type = 'news', $queue = 'queue2')
    {
        try {
            $wechatApp = Yii::$app->get('wechatApp')->setScenario($scenario);
            $user = null;
            if ($id === 0 && !$this->isGuest) {
                $user = $this->user;
            } elseif ($id instanceof Identity) {
                $user = WechatUser::findOne($id->id);
            } elseif ($id instanceof WechatUser) {
                $user = $id;
            } elseif (is_numeric($id)) {
                $user = WechatUser::findOne($id);
            } elseif (substr($id, 0, 7) === 'OPENID:') {
                // OPENID: 开头 创建虚拟用户
                $user = new WechatUser([
                    'openid' => substr($id, 7),
                    'time_active' => time(),
                ]);
                $user->isFollow = true;
            }
            if ($user && $user->allowSendCustomer) {
                if (!$queue) {
                    $wechatApp->sendCustomer($user->openid, $data, $type);
                } else {
                    Yii::$app->get($queue)->pushSendCustomer($scenario, $user->openid, $data, $type);
                }
                return true;
            }
        } catch (\Exception $e) {
            Yii::error('[sendCustomer] ' . $e->getMessage());
        }
        return false;
    }

    /**
     * 给当前用户 发送微信通知 小程序和服务号（模板消息）
     * @param int|Identity|WechatUser $id 需要通知的用户，写0等于当前用户自己。
     * @param string $scenario 发送消息的场景
     * @param string|array $url 跳转链接
     * @param string $template 模板ID
     * @param array $data
     * @param string $queue 使用队列
     * @return bool 是否通过初审（用队列的话只验证是否被添加到队列中，不保证一定成功）
     */
    public function sendTemplate($id, $scenario, $url, $template, $data = [], $queue = 'queue2')
    {
        try {
            $wechat = Yii::$app->get('wechatApp')->setScenario($scenario);
            $user = null;
            if ($id === 0 && !$this->isGuest) {
                $user = $this->user;
            } elseif ($id instanceof Identity) {
                $user = WechatUser::findOne($id->id);
            } elseif ($id instanceof WechatUser) {
                $user = $id;
            } elseif (is_numeric($id)) {
                $user = WechatUser::findOne($id);
            }
            /* $user \app\models\wechat\User */
            if ($user && $user->allowSendTemplate) {
                // 小程序自动追加 form_id
                if ($wechat->isWxapp) {
                    $formid = $user->formid;
                    if (!$formid) {
                        return false;
                    }
                    $data['form_id'] = $formid;
                }
                if (!$queue) {
                    $wechat->sendTemplate($user->openid, $url, $template, $data);
                } else {
                    Yii::$app->get($queue)->pushSendTemplate($scenario, $user->openid, $url, $template, $data);
                }
                return true;
            }
        } catch (\Exception $e) {
            Yii::error('[sendTemplate] ' . $e->getMessage());
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function getAccessChecker()
    {
        if ($this->accessChecker === null) {
            $this->accessChecker = Yii::createObject(PermChecker::class);
        }
        return $this->accessChecker;
    }

    // ------------------------------------------- 以下是各种场景的登陆方式 请勿滥用 -------------------------------------------

    /**
     * 在用户不存在微信 UnionId 的情况下，生成一个。
     * @param string $openid
     * @return string
     */
    private function createUnionId($openid)
    {
        return 'ENPTY#' . $openid . '#' . Yii::$app->wechatApp->scenario;
    }

    /**
     * 通过手机登陆
     * 如果用户不存在则抛出错误
     * @param string|int $phone
     * @param string|int $password 验证码或者密码 int认为是验证码 string认为是密码 弱类型语言 注意传惨类型
     * @param mixed $states 登录时的额外数据 暂存信息
     * @return Identity
     * @throws BadRequestHttpException
     */
    public function loginByPhone($phone, $password, $states = null)
    {

        $identity = Identity::findOne(['phone' => $phone]);
        $limit = null;
        try {
            if ($identity) {
                $limit = Yii::$app->user->can('limit', [
                    'key' => 'login_' . $phone,
                    'second' => 60 * 5,
                    'max' => 5,
                    // 'ip' => true, // 账号限制 不是IP限制
                ]);
                // MUGGLE_DEBUG_ENABLE 时直接放行
                if (!Muggle::isDebugEnv()) {
                    if (!$limit) { // 限制到了 直接试都不用试
                        throw new BadRequestHttpException('由于安全策略，当前账号已经被登录限制，请稍后再试。');
                    }
                    if (is_string($password)) {
                        if (!$identity->validatePassword($password)) {
                            throw new BadRequestHttpException('登陆失败，请检查用户名密码');
                        }
                    } elseif (is_int($password)) {
                        $validator = new PhoneValidator(['phoneValue' => $phone]);
                        if (!$validator->validate($password)) {
                            throw new BadRequestHttpException('登陆失败，请检查用户名密码');
                        }
                    } else {
                        throw new BadRequestHttpException('您提交的登录方式不正确');
                    }
                }
                $identity->time_active = time();
                $identity->ip = Muggle::clientIp();
                $identity->agent = substr(Yii::$app->request->userAgent, 0, 240);
                $identity->states = $states === null ? $phone : $states;
                $identity->save(false);
                // $identity->fixUniqueName();
                $this->login($identity);
                return $identity;
            } else {
                // 不能告诉他账号不存在
                if (is_string($password)) {
                    throw new BadRequestHttpException('登陆失败，请检查用户名密码');
                } elseif (is_int($password)) {
                    throw new BadRequestHttpException('登陆失败，请检查用户名密码');
                } else {
                    throw new BadRequestHttpException('您提交的登录方式不正确');
                }
            }
        } catch (BadRequestHttpException $e) {
            if ($limit instanceof \Closure) {
                $limit();
            }
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * 通过微信登录
     * 如果用户不存在则创建一个用户与之绑定
     * @param array $openid
     * @param array $unionid
     * @param mixed $states 登录数据 包含用户头像等信息
     * @return Identity
     */
    public function loginByWechat($openid, $unionid = null, $states = null)
    {
        $identity = null;
        // forced to disable slaves
        Yii::$app->db->enableSlaves = false;
        $user = WechatUser::findOne(['openid' => $openid]);
        /* @var $user \app\models\wechat\User */
        if ($user) {
            $identity = $user->identity;
        } else {
            if ($unionid === null) {
                $unionid = $this->createUnionId($openid);
            }
            $identity = Identity::findOne(['unionid' => $unionid]);
            // create one if not exists
            if ($identity === null) {
                // 没有用户信息时，不能创建通行证，当然这个可以改。
                if (!$states) {
                    throw new Exception('login failed, states is empty.');
                }
                $identity = new Identity([
                    'unionid' => $unionid,
                ]);
                $identity->loadDefaultValues();
            }
        }
        if ($states) {
            $identity->wechatAttributes = $states;
        }
        $identity->time_active = time();
        $identity->ip = Muggle::clientIp();
        $identity->agent = substr(Yii::$app->request->userAgent, 0, 240);
        if ($identity->save(false)) {
            if ($user === null) {
                $user = WechatUser::create($identity);
                $user->openid = $openid;
                $user->save(false);
            }
            if ($identity->isMigrateAvatar) {
                Yii::$app->queue2->pushMigrateWechatAvatar($identity->id);
            }
            $this->login($identity);
            return $identity;
        } else {
            throw new Exception('login failed, error: save false.');
        }
    }

    /**
     * 根据openid 查询对应的User用户 如果没有通行证 帮创建一个
     * 注意：只有微信消息推送使用此接口
     * @param WechatReplier $wx
     * @return Identity
     */
    public function findIdentityByWechatMessage(WechatReplier $wx)
    {
        // forced to disable slaves
        Yii::$app->db->enableSlaves = false;
        $openid = (string)$wx->in->FromUserName;
        // lock user join db
        $mutex = Yii::$app->mutex2;
        $mutexKey = 'WECHAT_MESSAGE_LOGIN_' . $openid;
        if (!$mutex->acquire($mutexKey, 10)) {
            throw new \Exception('mutex lock fail.');
        }
        $user = WechatUser::findOne(['openid' => $openid]);
        /* @var $user \app\models\wechat\User */
        if ($user) {
            $identity = $user->identity;
        } else {
            $states = Yii::$app->wechatApp->fetchUserInfo($openid);
            if (isset($states['subscribe']) && $states['subscribe'] === 0) {
                // 理应不该出现这种情况 用户没关注 怎么会被调用？
                // 有一种可能 是数据库没数据 然后他取关了
                // throw new NotAcceptableHttpException('not subscribe');
                $wx->asEmpty();
                exit(0);
            }
            if (!isset($states['unionid'])) {
                // throw new Exception('unionid does not exist.');
                $states['unionid'] = $this->createUnionId($openid);
            }
            $identity = Identity::findOne(['unionid' => $states['unionid']]);
            if ($identity === null) {
                // 没有通行证 创建一个
                $identity = new Identity([
                    'unionid' => $states['unionid'],
                    'agent' => 'wechat/message',
                ]);
                $identity->loadDefaultValues();
            }
            $identity->wechatAttributes = $states;
            if ($identity->save(false)) {
                $user = WechatUser::create($identity);
                $user->openid = $openid;
                $user->time_active = time();
                $user->time_subscribe = $states['subscribe_time'];
                $user->isFollow = true;
                $identity->link('user', $user);
            } else {
                $wx->asEmpty();
                exit(0);
            }
        }
        if ($identity->isMigrateAvatar) {
            Yii::$app->queue2->pushMigrateWechatAvatar($identity->id);
        }
        $this->setIdentity($identity);
        return $identity;
    }
}
