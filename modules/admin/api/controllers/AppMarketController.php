<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\api\controllers;

use app\components\File;
use app\models\AppMarket;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\modules\api\controllers\Format;
use Yii;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;

/**
 * App Market
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - AppMarket", description="管理员 - AppMarket")
 */
class AppMarketController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST appmarket' => 'create',
            'DELETE appmarket/<id:\d+>' => 'delete',
            'PUT appmarket/<id:\d+>' => 'update',
            // 'GET appmarket/<id:\d+>' => 'find', // 用户端的
            'GET appmarket' => 'index',
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
                'roles' => ['%APP_MARKER'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * 获取 AppMarket 列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/appmarket",
     *     tags={"Admin - AppMarket"},
     *     description="返回 appmarket 列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminMarket")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex()
    {
        $all = AppMarket::find()->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = Format::AppMarket($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 删除一个 AppMarket
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/appmarket/{id}",
     *     tags={"Admin - AppMarket"},
     *     description="删除一个AppMarket",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if (!$model->delete()) {
            throw new Exception($model->getFirstErrors(), '删除失败');
        }
    }

    /**
     * 新增一个 AppMarket
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/appmarket",
     *     tags={"Admin - AppMarket"},
     *     description="新增一个 AppMarket",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminMarketCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminMarket")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionCreate()
    {
        $model = new AppMarket();
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                $errors = $model->getFirstErrors();
                throw new Exception($errors, '添加失败 ' . current($errors));
            } else {
                return Format::AppMarket($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '添加失败 ' . $e->getName());
        }
    }

    /**
     * 修改 AppMarket
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/appmarket/{id}",
     *     tags={"Admin - AppMarket"},
     *     description="更新",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminMarketCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminMarket")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '修改失败');
            } else {
                return Format::AppMarket($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '修改失败' . $e->getName());
        }
    }

    /**
     * 表单载入模型
     * @param Keyword $model
     * @SWG\Definition(
     *     definition="AdminMarketCreate",
     *     type="object",
     *     @SWG\Property(property="name", type="string", description="APP名字", example="ofo单车"),
     *     @SWG\Property(property="android", type="integer", description="安卓下载地址 给附件中的ID", example=1),
     *     @SWG\Property(property="ios", type="integer", description="IOS下载地址 给附件中的ID", example=2),
     * )
     */
    private function setAttributes($model)
    {
        $params = Yii::$app->request->bodyParams;
        $model->attributes = $params;
    }

    /**
     * 查找模型
     * @param int $id
     * @return AppMarket
     */
    private function findModel($id)
    {
        $model = AppMarket::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('APP不存在');
        } else {
            return $model;
        }
    }
}
