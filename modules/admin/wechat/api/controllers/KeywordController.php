<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\wechat\api\controllers;

use app\modules\api\MetaResponse;
use app\modules\api\Exception;
use app\models\wechat\WechatKeyword;
use Yii;
use yii\web\NotFoundHttpException;
use yii\db\IntegrityException;
use yii\filters\AccessControl;

/**
 * 管理后台 微信关键词控制器
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Wechat", description="管理员 - 微信相关")
 *
 */
class KeywordController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE keyword/<scenario:[\w-]+>/<id:\d+>' => 'delete',
            'PUT keyword/<scenario:[\w-]+>/<id:\d+>' => 'update',
            'POST keyword/<scenario:[\w-]+>' => 'create',
            'GET keyword/<scenario:[\w-]+>' => 'all',
        ];
    }
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [[
                'allow' => true,
                'roles' => ['%WECHAT'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * 新增关键词
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/wechat/keyword/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="新增关键词",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatKeywordCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatKeyword")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionCreate($scenario)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        $model = new WechatKeyword();
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                $errors = $model->getFirstErrors();
                throw new Exception($errors, '添加失败 ' . current($errors));
            } else {
                return Format::keyword($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '关键词已存在');
        }
    }

    /**
     * 修改关键词
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/wechat/keyword/{scenario}/{id}",
     *     tags={"Admin - Wechat"},
     *     description="更新关键词",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatKeywordCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatKeyword")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionUpdate($scenario, $id)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        $model = $this->findModel($id);
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '修改失败');
            } else {
                return Format::keyword($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '修改失败' . $e->getName());
        }
    }

    /**
     * 删除关键词
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/wechat/keyword/{scenario}/{id}",
     *     tags={"Admin - Wechat"},
     *     description="删除一个关键词",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionDelete($scenario, $id)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        $model = $this->findModel($id);
        if (!$model->delete()) {
            throw new Exception($model->getFirstErrors(), '删除失败');
        }
    }

    /**
     * 获取关键词列表
     * @return array
     *
     * @SWG\Get(path="/admin/wechat/keyword/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="返回 scenario 公众号的关键词列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Parameter(in="query", name="q", type="string", description="查询", default="差评"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatKeyword")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionAll($scenario)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        $query = WechatKeyword::find();
        $q = Yii::$app->request->get('q');
        if (!empty($q)) {
            $query->andWhere(['like', 'keyword', $q]);
        }
        $all = $query->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = Format::keyword($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 表单载入模型
     * @param WechatKeyword $model
     *
     * @SWG\Definition(
     *     definition="AdminWechatKeywordCreate",
     *     type="object",
     *     @SWG\Property(property="isMatch", type="boolean", description="是否模糊匹配", example=true),
     *     @SWG\Property(property="keyword", type="string", description="关键词", example="差评君"),
     *     @SWG\Property(property="media_id", type="integer", description="媒体ID", example=5),
     * )
     */
    private function setAttributes($model)
    {
        $request = Yii::$app->request;
        $params = $request->getBodyParams();
        $media_ids = $request->getBodyParam('media_id');
        if (is_array($media_ids)) {
            $params['media_id'] = implode(',', $media_ids);
        }
        $model->attributes = $params;
    }

    /**
     * 查找模型
     * @param int $id
     * @return WechatKeyword
     */
    private function findModel($id)
    {
        $model = WechatKeyword::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('关键词不存在');
        } else {
            return $model;
        }
    }
}
