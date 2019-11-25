<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\modules\admin\api\controllers;

use app\models\ShortUrl;
use app\models\TurnPageTrait;
use app\modules\api\controllers\Controller;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use yii\db\IntegrityException;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use Yii;

/**
 * Admin Short Url
 *
 * @author fangjiali <root@fangjiali>
 * @SWG\Tag(name="Admin - Short - Url", description="短网址")
 */
class ShortUrlController extends Controller
{
    use TurnPageTrait;

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE shorturl/<id:\w+>' => 'delete',
            'POST shorturl' => 'create',
            'GET shorturl' => 'index',
        ];
    }

    /**
     * @inheritdoc
     * # -> root, * -> super admin,
     * % -> module admin, @@ -> registered user
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [[
                'allow' => true,
                'roles' => ['%%'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * 删除短链接
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/shorturl/{id}",
     *     tags={"Admin - Short - Url"},
     *     description="删除短链接",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="短链接不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = ShortUrl::findOne($id);
        if ($model) {
            return $model->delete();
        } else {
            throw new NotFoundHttpException('短链接不存在');
        }
    }

    /**
     * 获取短链接列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/shorturl",
     *     tags={"Admin - Short - Url"},
     *     description="返回短链接列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="id", type="string", description="ID", example="3laee2"),
     *             @SWG\Property(property="url", type="string", description="完整的原URL", example="test.com"),
     *             @SWG\Property(property="time_create", type="string", description="创建时间", example="2018-05-03 07:37:05"),
     *             @SWG\Property(property="realUrl", type="string", description="真实网址", example="test.com"),
     *             @SWG\Property(property="shortUrl", type="string", description="短网址", example="t.com"),
     *             @SWG\Property(property="count", type="integer",  description="访问次数"),
     *         )),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex()
    {
        $model = ShortUrl::find();
        $all = $this->getPages($model, 'time_create');
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->Format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="ShortUrl",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *     @SWG\Property(property="url", type="string", description="完整的原URL"),
     *     @SWG\Property(property="time_create", type="string", description="创建时间"),
     *     @SWG\Property(property="realUrl", type="string",  description="真实网址"),
     *     @SWG\Property(property="shortUrl", type="string",  description="短网址"),
     *     @SWG\Property(property="count", type="integer",  description="访问次数"),
     * )
     */
    private function Format(ShortUrl $model)
    {
        $result = [
            'id' => $model->id,
            'url' => $model->url,
            'time_create' => date('Y-m-d H:i:s', $model->time_create),
            'realUrl' => $model->realUrl,
            'shortUrl' => $model->shortUrl,
            'count' => $model->count,
        ];
        return $result;
    }

    /**
     * 创建短网址
     *
     * @SWG\Definition(
     *     definition="shortUrlCreate",
     *     type="object",
     *     @SWG\Property(property="url", type="string", description="完整的原URL", example="test.com"),
     * )
     *
     * @SWG\Post(path="/admin/shorturl",
     *     tags={"Admin - Short - Url"},
     *     description="创建短网址",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/shortUrlCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/ShortUrl")
     *     )
     * )
     */
    public function actionCreate()
    {
        $url = Yii::$app->request->post('url');
        try {
            $model = ShortUrl::create($url);
        } catch (IntegrityException $e) {
            throw new Exception($e->getMessage(), '操作失败 ' . $e->getName());
        }
        $result = $this->Format($model);
        return $result;
    }
}
