<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\api;

use app\components\Muggle;
use app\models\AdminBehaviorLog;
use Yii;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Admin API 模块
 *
 * @author William Chan <root@williamchan.me>
 */
class Module extends \yii\base\Module
{

    /**
     * @inheritdoc
     */
    public function createController($route)
    {
        $this->beforeCreateController($route);
        return parent::createController($route);
    }

    /**
     * 新增 Response 事件监听
     * @param string $route
     */
    protected function beforeCreateController($route)
    {
        static $first = false;
        if ($first === false) {
            Yii::$app->response->on(Response::EVENT_BEFORE_SEND, [$this, 'beforeResponseSend']);
            $first = true;
        }
    }

    /**
     * 自动添加 Admin 权限
     * 防止漏写权限
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $user = Yii::$app->user;
        // enableAutoBearer 会尝试自动登录 自动 Bearer 认证
        // 这里要注意 Yii-Debug 的用法不规范 导致一些意外情况 所以开发环境中 会一直被登录
        if ($user->isGuest) { // 只验证无状态的 有状态的不需要验证
            $user->enableAutoBearer = true;
            $user->enableSession = false;
        }
        if (!$user->isAdmin('%')) {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
        return true;
    }

    /**
     * API访问日志记录
     * @param \yii\base\Event $event
     */
    protected function beforeResponseSend($event)
    {
        $response = $event->sender;
        /* @var $response \yii\web\Response */
        if ($response->format === Response::FORMAT_JSON && Yii::$app->errorHandler->exception === null) {
            // 当前执行的是 API 控制器
            // 且控制器不是日志
            // 且是管理员以上的用户
            if (
                Yii::$app->controller &&
                Yii::$app->controller->uniqueId != 'api/admin/log' &&
                Yii::$app->user->isAdmin('%')
            ) {
                $request = Yii::$app->request;
                $response = Yii::$app->response;
                $log = new AdminBehaviorLog([
                    'identity_id' => Yii::$app->user->id,
                    'url' => $request->getPathInfo(),
                    'method' => $request->getMethod(),
                    'status' => $response->getStatusCode(),
                    'states' => $request->bodyParams,
                    'ip' => Muggle::clientIp(),
                    'agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                ]);
                $log->save();
            }
        }
    }
}
