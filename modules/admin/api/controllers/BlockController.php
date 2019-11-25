<?php
/**
 * This file is part of the yii-framework-base.
 * @auther caicai
 */
namespace app\modules\admin\api\controllers;

use app\components\Html;
use app\models\Block;
use app\models\Identity;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\modules\user\api\controllers\FormatIdentity;
use Yii;
use yii\helpers\Json;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;

/**
 * Block名单机制
 *
 * @author caicai <caicai@comteck.cn>
 * @SWG\Tag(name="Admin - Block", description="管理员 - 黑名单管理")
 */
class BlockController extends \app\modules\api\controllers\Controller
{
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE block/<id:\d+>' => 'delete',
            'PUT block/<id:\d+>' => 'update',
            'GET block' => 'index',
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
     * 获取黑名单列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/block",
     *     tags={"Admin - Block"},
     *     description="返回黑名单列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Block")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex()
    {
        $all = Block::find()->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->Format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 删除黑名单
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/block/{id}",
     *     tags={"Admin - Block"},
     *     description="删除黑名单",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="黑名单用户不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = $this->findBlock($id);
        if (!$model->delete()) {
            throw new Exception($model->getFirstErrors(), '删除失败');
        }
    }

    /**
     * 修改黑名单
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/block/{id}",
     *     tags={"Admin - Block"},
     *     description="更新",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/BlockUpdate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/Block")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        try {
            $model = $this->findBlock($id);
        } catch (NotFoundHttpException $e) {
            $identity = Identity::findOne($id);
            if (!$identity) {
                throw new NotFoundHttpException('没有这个用户');
            }
            $model = Block::loadFor($id);
        }
        $this->setAttributes($model);
        if ($model->save()) {
            return $this->Format($model);
        } else {
            throw new Exception($model->getFirstErrors(), '操作失败');
        }
    }

    /**
     * 表单载入模型
     * @param Block $model
     *
     * @SWG\Definition(
     *     definition="BlockUpdate",
     *     type="object",
     *     @SWG\Property(property="flags", ref="#/definitions/Flags"),
     *     @SWG\Property(property="isGlobal", type="boolean", description="是否全局"),
     * )
     */
    private function setAttributes($model)
    {
        $params = Yii::$app->request->bodyParams;
        $model->attributes = $params;
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="Block",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *     @SWG\Property(property="user", ref="#/definitions/UserBasic"),
     *     @SWG\Property(property="timeCreate", type="int",  description="添加时间"),
     *     @SWG\Property(property="flags", ref="#/definitions/Flags"),
     *     @SWG\Property(property="isGlobal", type="boolean",  description="是否全局"),
     *     @SWG\Property(property="summary", type="string", description="摘要", example="全局黑名单"),
     * )
     */
    private function Format($model)
    {
        $result = [
            'id' => $model->id,
            'user' => FormatIdentity::basic($model->identity),
            'timeCreate' => Html::humanTime($model->time_create),
            'flags' => $model->flags,
            'summary' => $model->summary,
            'isGlobal' => $model->isGlobal,

        ];
        return $result;
    }

    /**
     * 查找黑名单
     * @param int $id 用户ID
     * @throws \yii\web\HttpException
     * @return Block
     */
    private function findBlock($id)
    {
        $model = Block::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('这个用户不存在黑名单内');
        } else {
            return $model;
        }
    }
}
