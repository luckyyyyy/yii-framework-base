<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api;

use app\modules\UrlRule;
use Yii;
use yii\base\BootstrapInterface;
use yii\web\Response;

/**
 * API 模块
 *
 * @author William Chan <root@williamchan.me>
 */
class Module extends \yii\base\Module implements BootstrapInterface
{

    /**
     * 注册 url 规则集
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application) {
            $app->getUrlManager()->addRules([[
                'class' => UrlRule::class,
                'module' => $this,
            ]], false);
        }
    }

    /**
     * @param string $route
     * @return array|bool
     */
    public function createController($route)
    {
        $this->beforeCreateController($route);
        return parent::createController($route);
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        Yii::$app->user->registerUrl = null;
        if (Yii::$app->request->getMethod() === 'OPTIONS') {
            Yii::$app->end();
        }
        return parent::beforeAction($action);
    }

    /**
     * 格式化响应数据
     * @param mixed $data
     * @param \Exception $exception
     * @return array
     */
    public function formatResponseData($data, $exception = null)
    {
        if ($exception !== null) {
            $result = [];
            if (isset($data['status'])) {
                $result['errcode'] = $data['code'] > 0 ? $data['status'] * 100 + $data['code'] : $data['status'];
            } else {
                $result['errcode'] = 500;
            }
            $result['errmsg'] = $exception->getMessage();
            if (isset($data['stack-trace'])) {
                $result['stack-trace'] = $data['stack-trace'];
                array_unshift($result['stack-trace'], '## ' . $data['file'] . '(' . $data['line'] . ')');
            }
            if (isset($data['error-info'])) {
                $result['error-info'] = $data['error-info'];
            }
            $result['data'] = $exception instanceof Exception ? $exception->data : null;
            return $result;
        } elseif ($data instanceof MetaResponse) {
            return $data->formatData();
        } else {
            if (!Yii::$app->response->isRedirection) {
                return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
            }
        }
    }

    /**
     * @param string $route
     */
    protected function beforeCreateController($route)
    {
        static $first = false;
        if ($first === false) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            Yii::$app->response->on(Response::EVENT_BEFORE_SEND, [$this, 'beforeResponseSend']);
            Yii::$app->view; // init for alias, such as: @web/uploads
            Yii::$app->user->enableAutoBearer = false;
            $first = true;
        }
    }

    /**
     * 接口数据统一处理 CORS
     * @param \yii\base\Event $event
     */
    protected function beforeResponseSend($event)
    {
        $response = $event->sender;
        /* @var $response \yii\web\Response */
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Origin', isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*');
        if (Yii::$app->request->getMethod() === 'OPTIONS') {
            $response->setStatusCode(204, 'No Content');
            $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, Accept-Language, Content-Language, Authorization');
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                $response->headers->set('Access-Control-Allow-Methods', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']);
            }
        }
        if ($response->format === Response::FORMAT_JSON) {
            $response->data = $this->formatResponseData($response->data, Yii::$app->errorHandler->exception);
        }
    }
}
