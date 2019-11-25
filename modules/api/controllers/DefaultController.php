<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api\controllers;

use app\components\File;
use app\components\Html;
use app\models\Attachment;
use app\models\AppMarket;
use app\modules\UrlRule;
use app\modules\api\MetaResponse;
use app\modules\api\Module;
use app\modules\api\Exception;
use Yii;
use yii\db\Expression;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use Endroid\QrCode\QrCode;

/**
 * 默认
 * @author William Chan <root@williamchan.me>
 * @SWG\Parameter(parameter="wechatScenario", description="微信业务场景值，并不是所有类型的账号都支持该接口，包括了小程序/订阅号/服务号/微信APP/网站APP", in="path", required=true, name="scenario", type="string", enum={"A", "B", "C", "D", "E", "F", "G"}),
 */
class DefaultController extends Controller
{

    /**
     * @var array 扩展名
     */
    protected $extensions = ['jpg', 'png', 'gif', 'jpeg'];

    /**
     * @var int 文件最大尺寸
     */
    protected $maxSize = 10 * 1048576;

    /**
     * @var string 类型 决定文件上传路径
     */
    protected $type = null;

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST attachment/upload' => 'upload-file',
            'POST multi-requests' => 'multi-requests',
            'POST jssdk/config/<scenario:[\w-]+>' => 'jssdk-config',
            'GET appmarket/<id:\d+>' => 'find-appmarket',
            'GET short-url/<id:\w+>' => 'short-url',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function bearerConfig()
    {
        return [
            'optional' => ['multi-requests', 'jssdk-config', 'find-appmarket', 'test', 'short-url'],
        ];
    }

    /**
     * 文件上传
     * @param array $extensions 允许上传的扩展名
     * @return string
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/attachment/upload",
     *     tags={"Others"},
     *     summary="上传附件",
     *     description="通过 multipart/form-data 上传文件，返回文件名及网址",
     *     produces={"multipart/form-data"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="formData", name="file", type="file", description="上传文件实例，multipart/form-data 表单数据", required=true),
     *     @SWG\Parameter(in="formData", name="type", type="string", enum={"avatar"}, description="制定上传类型，留空则代表默认路径，影响后台保存路径。", default=0),
     *     @SWG\Parameter(in="formData", name="simditor", type="integer", enum={0, 1}, description="是否为 Simditor 兼容模式", default=0),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/attachmentOne")
     *     ),
     *     @SWG\Response(response=400, description="文件上传出错")
     * )
     */
    public function actionUploadFile()
    {
        $user = Yii::$app->user;
        /* @var $user = app/components/User */
        $req = Yii::$app->request;
        $simditor = $req->post('simditor');
        $config = [
            'extensions' => $this->extensions,
            'maxSize' => $this->maxSize,
        ];
        if ($this->type) {
            $config['type'] = $this->type;
        } elseif (in_array($req->post('type'), ['avatar'])) {
            $config['type'] = $req->post('type');
        }
        // uploaded file
        $file = new File($config);
        $result = $file->uploadCloud('file');
        if ($result) {
            if ($simditor) {
                return new MetaResponse(['success' => true, 'file_path' => Html::extUrl($file->fullPath)]);
            } else {
                $model = new Attachment([
                    'identity_id' => $user->id,
                    'type' => $file->type,
                    'name' => $file->upload->name,
                    'size' => $file->upload->size,
                    'mime' => $file->upload->type,
                    'path' => $file->fullPath,
                ]);
                $model->save();
                return Format::attachmentOne($model);
            }
        } else {
            if ($simditor) {
                return new MetaResponse(['success' => false, 'msg' => $file->getLastError()]);
            } else {
                throw new Exception($file->getLastError());
            }
        }
    }

    /**
     * Jssdk 签名配置
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/jssdk/config/{scenario}",
     *     tags={"Others"},
     *     summary="微信 JSSDK 配置",
     *     description="为客户端 jssdk 生成签名配置数据",
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="formData", name="url", type="string", description="需要签名的完整 URL，或以 / 开头。", required=true),
     *     @SWG\Parameter(in="formData", name="apis", type="string", description="需要签名的 API 列表，多个之间以逗号分隔", default=""),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             required={"appId", "timestamp", "nonceStr", "signature", "jsApiList"},
     *             @SWG\Property(property="debug", type="boolean", description="是否为调试模式", example=false),
     *             @SWG\Property(property="appId", type="string", description="公众号标识", example="wx7c0e03d79ac12847"),
     *             @SWG\Property(property="timestamp", type="integer", description="生成签名的时间戳", example=1503628396),
     *             @SWG\Property(property="nonceStr", type="string", description="生成签名的随机串", example="wjs_599f8c6c5f416"),
     *             @SWG\Property(property="signature", type="string", description="生成的签名", example="e5203c64a860062b55d26ba5d9ceea48f31f7146"),
     *             @SWG\Property(property="jsApiList", type="array", description="需要使用的JS接口列表",
     *                 @SWG\Items(type="string", example="uploadImage")
     *             )
     *         )
     *     ),
     *     @SWG\Response(response=400, description="提交数据有误")
     * )
     */
    public function actionJssdkConfig($scenario)
    {
        $wechat = Yii::$app->get('wechatApp');
        /* @var $wechat \app\components\WechatApp */
        $wechat->setScenario($scenario);
        $req = Yii::$app->request;
        $url = $req->post('url');
        if (empty($url)) {
            throw new BadRequestHttpException('签名 URL 必须提供');
        } elseif (substr($url, 0, 1) === '/') {
            $url = $req->hostInfo . $url;
        }
        $config = [];
        $apis = trim($req->post('apis'));
        if (!empty($apis)) {
            $config['jsApiList'] = explode(',', $apis);
        }
        return $wechat->getJsConfig($url, $config);
    }

    /**
     * 多请求代理
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/multi-requests",
     *     tags={"Others"},
     *     summary="多请求合并代理",
     *     description="合并多个请求，一次性返回全部结果，自动继承处理登录状态。",
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Parameter(in="body", name="body", description="多请求列表", required=true,
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/RequestItem"),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Error")
     *         )
     *     ),
     *     @SWG\Response(response=400, description="提交数据有误")
     * )
     * @SWG\Definition(
     *     definition="RequestItem",
     *     required={"method", "uri"},
     *     @SWG\Property(property="method", type="string", enum={"GET", "POST", "PUT", "DELETE"}, description="HTTP 方法", example="GET"),
     *     @SWG\Property(property="uri", type="string", description="API 路径", example="user/profile"),
     *     @SWG\Property(property="data", type="object", description="请求数据（哈希数组）")
     * )
     */
    public function actionMultiRequests()
    {
        // rule
        $apiRule = null;
        foreach (Yii::$app->urlManager->rules as $rule) {
            if ($rule instanceof UrlRule && $rule->module instanceof Module) {
                $apiRule = $rule;
                break;
            }
        }
        if (!$apiRule instanceof UrlRule) {
            throw new ServerErrorHttpException('UrlRule lost');
        }
        // handle all requests
        $items = [];
        // @fixme without restore request parameters
        $req = Yii::$app->request;
        $module = $this->module;
        $inputs = $req->bodyParams;
        foreach ($inputs as $input) {
            if (is_string($input)) {
                $input = ['method' => 'GET', 'uri' => $input];
            } elseif (!isset($input['method'], $input['uri'])) {
                $items[] = ['errcode' => 400, 'errmsg' => 'Bad Request'];
                continue;
            }
            $params = []; // query params
            if (($pos = strpos($input['uri'], '?')) !== false) {
                parse_str(substr($input['uri'], $pos + 1), $params);
                $input['uri'] = substr($input['uri'], 0, $pos);
            }
            // fake request
            $req->setPathInfo($module->id . '/' . ltrim($input['uri'], '/'));
            $_SERVER['REQUEST_METHOD'] = strtoupper($input['method']);
            switch ($req->getMethod()) {
                case 'GET':
                case 'DELETE':
                    if (isset($input['data']) && is_array($input['data'])) {
                        $params = array_merge($params, $input['data']);
                    }
                    break;
                case 'POST':
                case 'PUT':
                    $req->setBodyParams(isset($input['data']) ? $input['data'] : []);
                    break;
            }
            $info = $apiRule->parseRequest(Yii::$app->urlManager, $req);
            if ($info === false) {
                $info = [$req->pathInfo, []];
            }
            $params = array_merge($params, $info[1]);
            $_GET = $params;
            try {
                $route = substr($info[0], strlen($module->id) + 1);
                $data = $this->module->runAction($route, $params);
                $items[] = $this->module->formatResponseData($data);
            } catch (\yii\web\HttpException $e) {
                $items[] = ['errcode' => $e->statusCode, 'errmsg' => $e->getMessage()];
            } catch (\Exception $e) {
                $items[] = ['errcode' => 500, 'errmsg' => $e->getMessage()];
            }
        }
        return $items;
    }

    /**
     * 查找一个 App Market
     * @param [type] $id
     * @return void
     *
     * @SWG\Get(path="/appmarket/{id}",
     *     tags={"Others"},
     *     description="返回一个 appmarket",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="id", required=true, default=1),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminMarket")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionFindAppmarket($id)
    {
        $model = AppMarket::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('APP不存在');
        } else {
            return Format::appMarket($model);
        }
    }
}
