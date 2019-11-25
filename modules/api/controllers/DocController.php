<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api\controllers;

use app\components\Muggle;
use Yii;
use yii\helpers\Json;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;

/**
 * Swagger 文档控制器
 *
 * @author William Chan <root@williamchan.me>
 */
class DocController extends \yii\web\Controller
{

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        Yii::$app->user->enableAutoBearer = true;
        if (Yii::$app->user->isGuest && !Muggle::isDebugEnv()) {
            list($username, $password) = Yii::$app->request->getAuthCredentials();
            if ($username !== 'muggle' || $password !== '@internaldoc') {
                Yii::$app->response->headers->set('WWW-Authenticate', 'Basic realm="Muggle Server"');
                throw new UnauthorizedHttpException('Your request was made with invalid credentials.');
            }
        }
        return parent::beforeAction($action);
    }

    /**
     * 文档首页
     * @return string
     */
    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_HTML;
        return $this->renderFile('@app/modules/api/swagger.php');
    }

    /**
     * 文档首页 v2
     * @return string
     */
    public function actionV2()
    {
        Yii::$app->response->format = Response::FORMAT_HTML;
        return file_get_contents('https://doc.muggle-dev.com', false, stream_context_create(['ssl' => ['verify_peer' => false]]));
    }

    /**
     * 文档 JSON 数据
     * @return array
     */
    public function actionJson()
    {
        $cacheKey = 'global.api.json';
        $swagger = Yii::$app->cache->get($cacheKey);
        if (YII_ENV_DEV || $swagger === false) {
            $scanDir = $this->getScanDir($this->module);
            $scanOptions = [];
            $swagger = \Swagger\scan($scanDir, $scanOptions);
            // save cache
            if (!YII_ENV_DEV) {
                Yii::$app->cache->set($cacheKey, $swagger);
            }
        }
        // data fixes
        $req = Yii::$app->request;
        if ($swagger->host !== null) {
            $hostInfo = $req->hostInfo;
            $swagger->host = substr($hostInfo, strpos($hostInfo, '://') + 3);
            $swagger->basePath = substr($req->url, 0, -9);
        }
        if (!$req->isSecureConnection) {
            $swagger->schemes = ['http', 'https'];
        }
        // result as json
        $this->initCors();
        Yii::$app->response->format = Response::FORMAT_RAW;
        return Json::encode($swagger);
    }

    /**
     * init cors.
     */
    private function initCors()
    {
        $headers = Yii::$app->getResponse()->getHeaders();
        $headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $headers->set('Access-Control-Allow-Methods', 'GET, POST, DELETE, PUT');
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Allow', 'OPTIONS, HEAD, GET');
    }

    /**
     * 获取源码文档扫描目录
     * @param \yii\base\Module $module
     * @return array
     */
    private function getScanDir($module)
    {
        $dirs = [$module->basePath];
        foreach (array_keys($module->modules) as $id) {
            $dirs = array_merge($dirs, $this->getScanDir($module->getModule($id)));
        }
        return array_unique($dirs);
    }
}
