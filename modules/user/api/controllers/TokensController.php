<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\user\api\controllers;

use app\components\Muggle;
use app\components\PhoneValidator;
use app\models\Identity;
use app\models\AdminLoginLog;
use app\models\Token;
use app\modules\api\Exception;
use Yii;
use yii\web\Response;
use yii\web\HttpException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * 用户登录和注册相关
 *
 * @author William Chan <root@williamchan.me>
 *
 * @SWG\Tag(name="Tokens", description="登陆和授权相关")
 * @SWG\Definition(
 *     definition="WechatToken",
 *     required={"code"},
 *     @SWG\Property(property="scenario", type="string", description="不同的微信授权"),
 *     @SWG\Property(property="code", type="string", description="微信预授权码")

 * )
 * @SWG\Definition(
 *     definition="PhoneToken",
 *     required={"phone"},
 *     @SWG\Property(property="phone", type="string", description="手机号码"),
 *     @SWG\Property(property="code", type="string", description="验证码"),
 *     @SWG\Property(property="password", type="string", description="密码")
 * )
 * @SWG\Definition(
 *     definition="WxAppToken",
 *     required={"appid", "code", "iv", "encryptedData"},
 *     @SWG\Property(property="scenario", type="string", description="调用的小程序别名"),
 *     @SWG\Property(property="code", type="string", description="wx.login 返回的授权码"),
 *     @SWG\Property(property="iv", type="string", description="由 wx.getUserInfo({withCredentials:true}) 返回"),
 *     @SWG\Property(property="encryptedData", type="string", description="加密后的敏感数据，同上")
 * )

 */
class TokensController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST token' => 'create-token',
            'POST register' => 'register',
            'DELETE token' => 'delete-token',
            'POST login' => 'login',
            'GET oauth2/wechat/<scenario:[\w-]+>' => 'oauth2-wechat',
            'GET logout' => 'logout',
            'GET debug/<id:\d+>' => 'debug',
            'GET phone-code/<phone:\+?\d+>' => 'phone-code',

        ];
    }

    /**
     * @inheritdoc
     */
    protected function bearerConfig()
    {
        return [
            'only' => ['logout', 'delete-token'],
        ];
    }

    /**
     * 发送手机验证码
     * @param $phone
     * @param string $sign
     * @return array
     * @throws BadRequestHttpException
     * @throws Exception
     *
     * @SWG\Get(path="/user/phone-code/{phone}",
     *     tags={"Tokens"},
     *     description="自动根据IP和手机号限制获取频率。",
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Parameter(in="path", name="phone", type="string", description="手机号码", required=true),
     *     @SWG\Parameter(in="query", name="sign", type="string",
     *         enum={"A", "console"},
     *         description="短信签名，A，B", default="A"
     *     ),
     *     @SWG\Response(response=200, description="success 返回剩余秒数",
     *         @SWG\Schema(
     *             required={"leftTime"},
     *             @SWG\Property(property="leftTime", type="integer", description="距离下次发送剩余秒数", example=60)
     *         )
     *     ),
     *     @SWG\Response(response=400, description="手机号不正确"),
     *     @SWG\Response(response=406, description="请求过快，返回剩余秒数"),
     *     @SWG\Response(response=502, description="短信网关出错")
     * )
     */
    public function actionPhoneCode($phone, $sign = 'A')
    {
        $phone = strtr($phone, ['-' => '', ' ' => '']);
        if (!preg_match('/^\+?\d{6,}$/', $phone)) {
            throw new BadRequestHttpException('手机号不正确');
        }
        $validator = new PhoneValidator();
        if (($leftTime = $validator->getSendLeft()) > 0) {
            throw new Exception(['leftTime' => $leftTime], $leftTime . '秒后才能再发', 406);
        }
        // 暂时写死 可以用API传个小参判断
        $signName = $sign == 'A' ? "A" : "B";
        $error = $validator->sendCode($phone, $signName);
        if ($error !== 'OK') {
            throw new Exception($error, 'SMS-ERROR', 502);
        }
        return ['leftTime' => $validator->delayTime];
    }

    /**
     * 微信授权登陆 (Cookie based)
     *
     * @param string $scenario
     * @param string|null $redirect_uri
     * @param string|null $code
     * @return Response
     * @throws BadRequestHttpException
     * @throws NotAcceptableHttpException
     *
     *
     * @SWG\Get(path="/user/oauth2/wechat/{scenario}",
     *     tags={"Tokens"},
     *     description="微信客户端使用",
     *     security={},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="query", name="redirect_uri", type="string", description="授权后跳转到redirect_uri"),
     *     @SWG\Parameter(in="query", name="code", type="string", description="授权需要的code"),
     *     @SWG\Response(response=302, description="授权后跳转到redirect_uri"),
     * )
     */
    public function actionOauth2Wechat($scenario, $redirect_uri = null, $code = null)
    {
        // Output HTML, not the api format.
        Yii::$app->response->format = Response::FORMAT_HTML;
        // switch wechat scenario
        $wechat = Yii::$app->get('wechatApp')->setScenario($scenario);
        /* @var $wechat \app\components\WechatApp */
        // exchange token
        $user = Yii::$app->user;
        $states = null;
        /* @var $user \app\components\User */
        if ($redirect_uri) {
            // 这里可以安全过滤
            $user->setReturnUrl($redirect_uri);
        }
        if ($code) {
            try {
                $token = $wechat->fetchAccessToken($code);
                $user->enableSession = true;
                $openid = $token['openid'];
                $unionid = $token['unionid'] ?? null;
                $identity = Identity::findOne(['unionid' => $unionid]);
                try {
                    // 简化登陆 必须获取到 unionid
                    if (!$unionid) {
                        throw new \Exception('unionid is null');
                    }
                    $user->loginByWechat($openid, $unionid, null);
                } catch (\Exception $e) {
                    $states = $wechat->snsApi('userinfo', [
                        'access_token' => $token['access_token'],
                        'openid' => $openid,
                        'lang' => 'zh_CN',
                    ]);
                    $user->loginByWechat($openid, $unionid, $states);
                }
                $this->loginLog('wechat', $states);
                return $this->redirect($user->returnUrl);
            } catch (\Exception $e) {
                // 登录失败的情况 只有安卓机会出现 可奇葩了
                // return $this->redirect($user->returnUrl);
                throw new BadRequestHttpException('用户授权获取失败，请返回重试一次。');
            }
        }
        // 跳转第三方授权地址
        $params = [];
        if ($wechat->isWeb) {
            $params['login_type'] = 'jssdk';
            $params['self_redirect'] = 'default';
        } else {
            if (!Muggle::isWechat()) {
                throw new NotAcceptableHttpException('您请求的登录方式不正确，此页面应当在微信中访问。');
            }
        }
        return $this->redirect($wechat->fetchAuthUrl(null, $params));
    }

    /**
     * 创建 Bearer Token (Bearer Token)
     * @throws \yii\web\HttpException
     * @return array
     *
     * @SWG\Post(path="/user/token",
     *     tags={"Tokens"},
     *     description="提交客户端微信登录后的返回数据或使用手机号和验证码，向服务器换取 Bearer Token。",
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Parameter(in="body", name="body", description="登录数据，wechat/phone/wxapp 须提供一项", required=true,
     *         @SWG\Schema(
     *             required={},
     *             @SWG\Property(property="device", type="string", description="硬件设备ID", example="iPhone 9,2"),
     *             @SWG\Property(property="os_name", type="string", description="客户端系统名称", example="iOS"),
     *             @SWG\Property(property="os_version", type="string", description="客户端系统版本", example="9.3.2"),
     *             @SWG\Property(property="push_id", type="string", description="客户端推送 ID"),
     *             @SWG\Property(property="wechat", ref="#/definitions/WechatToken"),
     *             @SWG\Property(property="phone", ref="#/definitions/PhoneToken"),
     *             @SWG\Property(property="wxapp", ref="#/definitions/WxAppToken")
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             required={"access_token", "game", "registered"},
     *             @SWG\Property(property="access_token", type="string", description="已授权的 access_token，长期有效直至被注销"),
     *             @SWG\Property(property="expires_in", type="integer", description="多长时间后过期，-1表示不限", example=-1)
     *         )
     *     ),
     *     @SWG\Response(response=400, description="提交数据有误"),
     * )
     */
    public function actionCreateToken()
    {
        $user = Yii::$app->user;
        /* @var $user \app\components\User */

        $states = null;
        $params = Yii::$app->request->bodyParams;
        if (isset($params['wechat']['code'])) {
            // 微信授权码登录
            // $wechat = Yii::$app->get('wechatApp');
            /* @var $wechat \app\components\WechatApp */
            // TODO
            die;
        // $wechat->setScenario('微信开放平台 客户端登陆 需要申请的');
            // try {
            //     $token = $wechat->fetchAccessToken($params['wechat']['code']);
            //     $states = $wechat->snsApi('userinfo', [
            //         'access_token' => $token['access_token'],
            //         'openid' => $token['openid'],
            //         'lang' => 'zh_CN',
            //     ]);
            // } catch (\Exception $e) {
            //     throw new Exception($e->getMessage(), '服务器拒绝了您的请求');
            // }
            // $identity = $user->loginByWechat($token, $states);
        } elseif (isset($params['phone']['phone'])) {
            // 手机登陆
            $phone = $params['phone'];
            if (isset($phone['code'])) {
                $code = (int) $phone['code'];
            } elseif (isset($phone['password'])) {
                $code = (string) $phone['password'];
            } else {
                throw new BadRequestHttpException('提交数据有误');
            }
            $identity = $user->loginByPhone($phone['phone'], $code, [
                'phone' => $phone['phone'],
                'code' => $code,
                'mode' => 'token',
            ]);
        } elseif (isset($params['wxapp']['scenario'], $params['wxapp']['code'])) {
            $wxapp = $params['wxapp'];
            // 小程序登陆
            /* @var $wechat \app\components\WechatApp */
            $wechat = Yii::$app->get('wechatApp');
            $wechat->setScenario($wxapp['scenario']);
            try {
                $session = $wechat->fetchSession($wxapp['code']);
                if (isset($params['wxapp']['iv'], $params['wxapp']['encryptedData'])) {
                    $states = $wechat->decryptAesData($wxapp['encryptedData'], $wxapp['iv'], $session['session_key']);
                    $states = array_change_key_case($states, CASE_LOWER);
                    $openid = $states['openid'];
                    $unionid = $states['unionid'] ?? null;
                    $identity = $user->loginByWechat($openid, $unionid, $states);
                } else {
                    $openid = $session['openid'];
                    $unionid = $session['unionid'] ?? null;
                    // 简化登陆 必须获取到 unionid
                    if (!$unionid) {
                        throw new \Exception('unionid is null');
                    }
                    $identity = $user->loginByWechat($openid, $unionid);
                }
                if (!isset($params['os_name'])) {
                    $params['os_name'] = 'wxapp_' . $wechat->scenario;
                }
                $params['push_id'] = $wechat->scenario; // @TODO
            } catch (\Exception $e) {
                throw new Exception($e->getMessage(), '登陆失败，服务器拒绝了您的请求。');
            }
        } else {
            throw new BadRequestHttpException('Wechat/phone/wxapp must provide one.');
        }
        // result
        unset($params['wechat'], $params['phone'], $params['wxapp']);
        $token = new Token();
        $token->attributes = $params;
        $token->states = $states;
        $token->identity = $identity;
        $this->loginLog('token', $states);
        return [
            'access_token' => $token->id,
            'expires_in' => -1,
        ];
    }

    /**
     * 手机登录 (Cookie based)
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/user/login",
     *     tags={"Tokens"},
     *     description="使用手机帐号与验证码方式登录，二选一。",
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Parameter(in="body", name="body", description="登陆数据", required=true,
     *         @SWG\Schema(
     *             required={},
     *             @SWG\Property(property="phone", type="string", description="手机号码", example="1300000000"),
     *             @SWG\Property(property="code", type="string", description="验证码", example="123456"),
     *             @SWG\Property(property="password", type="string", description="密码", example="password"),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(type="object",
     *              allOf={
     *                  @SWG\Schema(ref="#/definitions/UserBasic"),
     *                  @SWG\Schema(ref="#/definitions/UserPerms"),
     *              }
     *          )
     *     ),
     *     @SWG\Response(response=400, description="提交数据有误"),
     * )
     */
    public function actionLogin()
    {
        $params = Yii::$app->request->bodyParams;
        if (!isset($params['phone']) || !trim($params['phone'])) {
            throw new BadRequestHttpException('提交数据有误');
        }
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        $user->enableSession = true;
        $phone = $params['phone'];
        if (isset($params['code'])) {
            $code = (int) $params['code'];
        } elseif (isset($params['password'])) {
            $code = (string) $params['password'];
        } else {
            throw new BadRequestHttpException('提交数据有误');
        }

        $states = [
            'phone' => $phone,
            'code' => $code,
            'mode' => 'session',
        ];
        $identity = $user->loginByPhone($phone, $code, $states);
        $this->loginLog('phone', $states);
        return array_merge(FormatIdentity::basic($identity), FormatIdentity::perms($identity));
    }

    /**
     * 注销 Bearer Token (Bearer Token)
     *
     * @SWG\Delete(path="/user/token",
     *     tags={"Tokens"},
     *     description="销毁已取得的 Bearer Token。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionDeleteToken()
    {
        $identity = Yii::$app->user->identity;
        /* @var $identity \app\models\Identity */
        if ($identity->token !== null) {
            $token = $identity->token;
            $token->push_id = null; // NEED NOT notify
            $token->delete();
        }
        Yii::$app->user->logout();
    }

    /**
     * 登陆任意用户 (Cookie based / Bearer Token)
     * @param int $id
     * @param string $redirect_uri
     * @param string $token
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Get(path="/user/debug/{id}",
     *     tags={"Tokens"},
     *     description="将当前 session 设置为{id}用户，只有测试服开放此接口，如果有redirect_uri则跳转，可以加referral。",
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="登陆的用户ID", required=true, default="1"),
     *     @SWG\Parameter(in="query", name="__from_uid", type="string", description="推荐ID，此选项会激发session。", default="0"),
     *     @SWG\Parameter(in="query", name="token", type="string", enum={"Y", "N"}, description="是否换取token 默认为session", default="N"),
     *     @SWG\Parameter(in="query", name="redirect_uri", type="string", description="只当token为N时 回跳 URL"),
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(type="object",
     *              allOf={
     *                  @SWG\Schema(ref="#/definitions/UserBasic"),
     *                  @SWG\Schema(ref="#/definitions/UserPerms"),
     *              }
     *          )
     *     ),
     *     @SWG\Response(response=404, description="用户不存在"),
     *     @SWG\Response(response=403, description="无权调用此接口"),
     *     @SWG\Response(response=302, description="跳转到redirect_uri"),
     * )
     */
    public function actionDebug($id, $redirect_uri = '', $token = 'N')
    {
        if (Muggle::isDebugEnv()) {
            $user = Yii::$app->user;
            /* @var $user \app\components\User */
            $identity = Identity::findIdentity($id);
            if ($identity === null) {
                throw new NotFoundHttpException('debug account#' . $id . ' dose not exists.');
            } else {
                if ($token === 'Y') {
                    $token = new Token();
                    $token->attributes = [
                        'os_name' => 'debug',
                        'os_version' => '0.0.0',
                    ];
                    $token->identity = $identity;
                    $this->loginLog('debug/token');
                    return [
                        'access_token' => $token->id,
                        'expires_in' => -1,
                    ];
                } else {
                    $user->enableSession = true;
                    $user->login($identity);
                    $this->loginLog('debug/session');
                }
            }
            if ($redirect_uri) {
                $user->setReturnUrl($redirect_uri);
                return $this->redirect($redirect_uri);
            } else {
                return array_merge(FormatIdentity::basic($identity), FormatIdentity::perms($identity));
            }
        } else {
            throw new ForbiddenHttpException();
        }
    }

    /**
     * 用户退出登录 (Cookie based)
     * @param string $redirect_uri
     * @return array
     *
     * @SWG\Get(path="/user/logout",
     *     tags={"Tokens"},
     *     description="一般为测试使用，可以退出当前用户，并不是销毁token，正式服也开放此接口。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="query", name="redirect_uri", type="string", description="回跳 URL"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=302, description="跳转到redirect_uri"),
     * )
     */
    public function actionLogout($redirect_uri = '')
    {
        $user = Yii::$app->user;
        $user->logout();
        if ($redirect_uri) {
            $user->setReturnUrl($redirect_uri);
            return $this->redirect($redirect_uri);
        }
    }


    /**
     * 注册通行证（子类可以考虑继承）
     *
     * ```php
     * public function actionRegister()
     * {
     *     if (parent::actionRegister()) {
     *         some code ...
     *     }
     *
     * }
     * ```
     * @throws HttpException
     * @SWG\Post(path="/user/register",
     *     tags={"Tokens"},
     *     description="使用手机号，验证码和密码注册一个通行证",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="登陆数据", required=true,
     *         @SWG\Schema(
     *             required={},
     *             @SWG\Property(property="phone", type="string", description="手机号码", example="1300000000"),
     *             @SWG\Property(property="code", type="string", description="验证码", example="123456"),
     *             @SWG\Property(property="password", type="string", description="密码", example="password"),
     *             @SWG\Property(property="scenario", type="string", description="场景", example="console"),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=400, description="数据错误"),
     * )
     */
    public function actionRegister()
    {
        $user = Yii::$app->user;
        // 一天最多注册 20 个账号 IP限制
        $limit = Yii::$app->user->can('limit', [
            'key' => 'REGISTER_IDENTITY',
            'second' => 86400,
            'max' => 20,
            'ip' => true,
        ]);
        if (!$limit) { // 限制到了 直接试都不用试
            throw new HttpException(444);
        }
        $params = Yii::$app->request->bodyParams;
        if (isset($params['phone']) && isset($params['password']) && isset($params['code'])) {
            $validator = new PhoneValidator(['phoneValue' => $params['phone']]);
            if (!$validator->validate($params['code'])) {
                throw new BadRequestHttpException('您输入的验证码不正确');
            }
            $identity = Identity::findOne(['phone' => $params['phone']]);
            if ($identity) {
                throw new BadRequestHttpException('手机号已被注册，请直接登录。');
            } else {
                $identity = new Identity;
            }
            $identity->setScenario(Identity::SCENARIO_REGISTER);
            $identity->attributes = $params;
            if (!$identity->name) {
                $identity->name = $identity->phone;
            }
            if ($identity->save()) {
                if ($limit instanceof \Closure) {
                    $limit();
                }
                return true;
            } else {
                throw new Exception($identity->getFirstErrors(), '注册失败');
            }
        } else {
            throw new BadRequestHttpException('您请求的参数不正确');
        }
    }

    /**
     * 记录管理员登陆日志
     * @param string $method 登陆方式
     * @param mixed $states 登陆数据
     */
    private function loginLog($method, $states = [])
    {
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        if ($user->isAdmin('%')) {
            $log = new AdminLoginLog([
                'identity_id' => $user->id,
                'method' => $method,
                'states' => $states,
                'ip' => Muggle::clientIp(),
                'agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            ]);
            $log->save();
        }
    }
}
