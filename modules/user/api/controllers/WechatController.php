<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\user\api\controllers;

use app\components\WechatApp;
use app\components\Html;
use app\models\Identity;
use app\models\wechat\WechatFormid;
use app\models\wechat\User;
use app\modules\api\Exception;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\BadRequestHttpException;
use yii\web\NotAcceptableHttpException;

/**
 * 用户相关 - 微信
 *
 * @author William Chan <root@williamchan.me>
 *
 * @SWG\Tag(name="User - Wechat", description="用户相关 - 微信")
 */
class WechatController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST wechat/formid/<scenario:[\w-]+>' => 'formid',
            'GET wechat/profile/<scenario:[\w-]+>' => 'profile',
            'GET wechat/bind/<scenario:[\w-]+>' => 'bind',
        ];
    }

    /**
     * 用户提交小程序推送专用的formid
     * @return bool
     *
     * @SWG\Post(path="/user/wechat/formid/{scenario}",
     *     tags={"User - Wechat"},
     *     description="用户提交小程序推送专用的formid",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="formid", type="string", description="formid", example="45754874823464"),
     *             @SWG\Property(property="type", type="integer", description="类型 1是普通表单 2是支付表单", example=1),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionFormid($scenario)
    {
        $wechat = Yii::$app->wechatApp;
        $wechat->scenario = $scenario;
        if (!$wechat->isWxapp) {
            throw new NotAcceptableHttpException('Scenario Not Acceptable');
        }
        $params = Yii::$app->request->bodyParams;
        $model = new WechatFormid;
        $model->attributes = $params;
        $model->user_id = Yii::$app->user->id;
        if (!$model->save()) {
            throw new BadRequestHttpException(current($model->getFirstErrors()));
        }
        return true;
    }

    /**
     * 获取公众号关注信息
     * @param string $strict
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Get(path="/user/wechat/profile/{scenario}",
     *     tags={"User - Wechat"},
     *     description="获取关注信息",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(type="object",
     *              @SWG\Property(property="isBind", type="boolean", description="用户是否已绑定微信", example=true),
     *              @SWG\Property(property="isFollow", type="boolean", description="是否关注（只有公众号有用 其他场景没意义）", example=true),
     *              @SWG\Property(property="user", type="object", description="绑定的昵称和头像，绑定了才显示",
     *                  @SWG\Property(property="name", type="string", description="名字", example="name"),
     *                  @SWG\Property(property="avatar", type="string", description="头像", example="/path/to"),
     *              ),
     *          )
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionProfile($scenario)
    {
        $wechat = Yii::$app->wechatApp->setScenario($scenario);
        $user = User::loadFor(Yii::$app->user->id);
        $result = [
            'isBind' => $user->isBind,
            'isFollow' => $user->isFollow,
            // 'timeJoin' => $user->time_join,
            // 'timeCancel' => time() - $user->time_cancel,
            // 'timeSubscribe' => time() - $user->time_subscribe,
            // 'leftSubscribe' => time() - $user->time_subscribe,
            // 'timeActive' => time() - $user->time_active,
            // 'timeActiveMenu' => time() - $user->time_active_menu,
        ];
        if ($user->isBind) {
            $result['info'] = [];
            if ($wechat->isMp) {
                $info = $wechat->fetchUserInfo($user->openid);
                $result['info']['name'] = $info['nickname'];
                $result['info']['avatar'] = $info['headimgurl'];
            } else {
                $identity = Yii::$app->user->identity;
                $result['info']['name'] = $identity->name;
                $result['info']['avatar'] = Html::extUrl($identity->avatar);
            }
        }
        return $result;
    }

    /**
     * 绑定微信(仅支持网页和App)
     * @param $scenario
     * @param null $redirect_uri
     * @param null $code
     * @return Response
     * @throws BadRequestHttpException
     *
     * @SWG\Get(path="/user/wechat/bind/{scenario}",
     *     tags={"User - Wechat"},
     *     description="# 绑定微信（三种模式如下）
     *         PC网页：直接访问这个页面不带code，或者弹框然后iframe嵌入这个地址，带上redirect_uri（当前页即可），不要带code，会自动给你二维码，扫码后自动刷新页面。
     *         微信内H5：直接302跳转到这个页面，带上redirect_uri，用户确认后送你回来
     *         APP：拿code来换，不要填redirect_uri，返回json API结果。",
     *     security={},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="query", name="redirect_uri", type="string", description="授权后跳转到redirect_uri"),
     *     @SWG\Parameter(in="query", name="code", type="string", description="授权需要的code"),
     *     @SWG\Response(response=302, description="授权后跳转到redirect_uri"),
     * )
     */
    public function actionBind($scenario, $redirect_uri = null, $code = null)
    {
        $wechat = Yii::$app->get('wechatApp');
        /* @var $wechat \app\components\WechatApp */
        $wechat->setScenario($scenario);
        if ($redirect_uri) {
            Yii::$app->response->format = Response::FORMAT_HTML;
        }
        if (!$code) {
            if ($redirect_uri) {
                $params = [];
                if ($wechat->isWeb) {
                    $params['login_type'] = 'jssdk';
                    $params['self_redirect'] = 'default';
                }
                return $this->redirect($wechat->fetchAuthUrl(null, $params));
            }
            throw new BadRequestHttpException('缺少必要参数');
        }
        $req = Yii::$app->request;
        $identity = Yii::$app->user->identity;
        /** @var $identity \app\models\Identity */
        // 换取 token 信息
        try {
            $token = $wechat->fetchAccessToken($code);
            $info = $wechat->snsApi('userinfo', [
                'access_token' => $token['access_token'],
                'openid' => $token['openid'],
                'lang' => 'zh_CN',
            ]);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('用户授权获取失败，请返回重试一次。');
        }
        if (!isset($info['unionid']) || !isset($info['openid'])) {
            throw new BadRequestHttpException('缺少相关参数，绑定失败。');
        }
        if ($identity->unionid && $identity->unionid !== $info['unionid']) {
            throw new BadRequestHttpException('当前用户已绑定了其他微信主账户，绑定失败。。');
        }
        if ($identity->user) {
            throw new BadRequestHttpException('当前用户已绑定了其他微信账户，绑定失败。。');
        }

        $identity->unionid = $info['unionid'];
        $identity->save(false);

        $user = User::loadFor($identity);
        $user->openid = $info['openid'];
        $user->save(false);
        if ($redirect_uri) {
            return $this->redirect($redirect_uri);
        }
    }

}
