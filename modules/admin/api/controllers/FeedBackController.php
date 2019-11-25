<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\modules\admin\api\controllers;

use app\components\Html;
use app\models\Feedback;
use app\models\TurnPageTrait;
use app\modules\api\controllers\Controller;
use app\modules\api\MetaResponse;
use app\modules\user\api\controllers\FormatIdentity;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

/**
 * Admin Feed Back
 *
 * @author fangjiali
 * @SWG\Tag(name="Admin - Feed - Back", description="反馈")
 */
class FeedBackController extends Controller
{
    use TurnPageTrait;

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE feedback/<id:\d+>' => 'delete',
            'PUT feedback/<id:\d+>' => 'update',
            'GET feedback/<id:\d+>' => 'detail',
            'GET feedback' => 'index',
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
     * 获取反馈列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/feedback",
     *     tags={"Admin - Feed - Back"},
     *     description="获取反馈列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *             @SWG\Property(property="type", type="integer", description="反馈类型", example="1"),
     *             @SWG\Property(property="typeLabel", type="string", description="反馈类型", example="参数错误"),
     *             @SWG\Property(property="isDeal", type="integer", description="是否处理", example=1),
     *             @SWG\Property(property="content", type="string",  description="反馈内容", example="内容"),
     *             @SWG\Property(property="attachment", type="array", description="attachment 数组",
     *                          @SWG\Items(type="object", example={"0":"/path/to"})
     *             ),
     *             @SWG\Property(property="identity",ref="#/definitions/UserBasic"),
     *             @SWG\Property(property="time_create", type="string",  description="创建时间", example="2017-09-01 14:21"),
     *         )),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex()
    {
        $model = Feedback::find()->with('identity');
        $all = $this->getPages($model, 'time_create');
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->Format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 查看反馈
     *
     * @SWG\Get(path="/admin/feedback/{id}",
     *     tags={"Admin - Feed - Back"},
     *     description="查看反馈",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *             @SWG\Property(property="type", type="integer", description="反馈类型", example="1"),
     *             @SWG\Property(property="typeLabel", type="string", description="反馈类型", example="参数错误"),
     *             @SWG\Property(property="isDeal", type="integer", description="是否处理", example=1),
     *             @SWG\Property(property="content", type="string",  description="反馈内容", example="内容"),
     *             @SWG\Property(property="attachment", type="array", description="attachment 数组",
     *                          @SWG\Items(type="object", example={"0":"/path/to"})
     *             ),
     *             @SWG\Property(property="identity",ref="#/definitions/UserBasic"),
     *             @SWG\Property(property="time_create", type="string",  description="创建时间", example="2017-09-01 14:21"),
     *         )),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionDetail($id)
    {
        $model = Feedback::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('反馈不存在');
        }
        return $this->Format($model);
    }

    /**
     * 删除反馈
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/feedback/{id}",
     *     tags={"Admin - Feed - Back"},
     *     description="删除反馈",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="反馈不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = Feedback::findOne($id);
        if ($model) {
            return $model->delete();
        } else {
            throw new NotFoundHttpException('反馈不存在');
        }
    }

    /**
     * 处理反馈
     *
     * @SWG\Put(path="/admin/feedback/{id}",
     *     tags={"Admin - Feed - Back"},
     *     description="处理反馈",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     * )
     */
    public function actionUpdate($id)
    {
        $model = Feedback::findOne($id);
        if ($model) {
            Feedback::updateAll(['isDeal' => 1], ['id' => $id]);
        } else {
            throw new NotFoundHttpException('反馈不存在');
        }
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="FeedBack",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *     @SWG\Property(property="type", type="integer", description="反馈类型", example="1"),
     *     @SWG\Property(property="typeLabel", type="string", description="反馈类型", example="参数错误"),
     *     @SWG\Property(property="isDeal", type="integer", description="是否处理", example=1),
     *     @SWG\Property(property="content", type="string",  description="反馈内容", example="内容"),
     *     @SWG\Property(property="attachment", type="array", description="attachment 数组",
     *                   @SWG\Items(type="object", example={"0":"/path/to"})
     *     ),
     *     @SWG\Property(property="identity",ref="#/definitions/UserBasic"),
     *     @SWG\Property(property="time_create", type="string",  description="创建时间", example="2017-09-01 14:21"),
     * )
     */
    private function Format(Feedback $model)
    {
        $result = [
            'id' => $model->id,
            'type' => $model->type,
            'typeLabel' => $model->typeLabel,
            'isDeal' => $model->isDeal,
            'content' => $model->content,
            'attachment' => Html::extUrl($model->attachment),
            'identity' => FormatIdentity::basic($model->identity), // 前台页面需要
            'time_create' => date('Y-m-d H:i', $model->time_create),
        ];

        return $result;
    }
}
