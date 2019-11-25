<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\modules\admin\api\controllers;

use app\components\Html;
use app\models\Admin;
use app\models\Identity;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\modules\user\api\controllers\FormatIdentity;
use Yii;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;

/**
 * Admin Manager
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Manager", description="管理员 - 管理员管理")
 */
class AdminManagerController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE manager/<id:\d+>' => 'delete',
            'PUT manager/<id:\d+>' => 'update',
            'GET manager' => 'index',
            'GET flags' => 'flags',
            'GET flags-map' => 'map-flags',
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
                'roles' => ['*'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * 获取当前管理员列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/manager",
     *     tags={"Admin - Manager"},
     *     description="返回管理员列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *      @SWG\Parameter(in="query", name="flag", type="string", description="flag"),
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminManager")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex($flag = null)
    {
        $query = Admin::find();
        if (!empty($flag)) {
            $query->where(['&', 'flag', $flag]);
        }
        $all = $query->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->Format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 获取管理员所有类型
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/flags",
     *     tags={"Admin - Manager"},
     *     description="返回管理员所有类型",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="key", type="string", description="管理员FLAG 键值对", example="众测管理员")
     *         )

     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionFlags()
    {
        $flags = Admin::flagOptions();
        $flags[Admin::FLAG_SUPER] = "超级管理员";

        return $flags;
    }

    /**
     * 获取管理员所有类型
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/flags-map",
     *     tags={"Admin - Manager"},
     *     description="返回管理员所有类型集合",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="key", type="string", description="管理员FLAG 键值对", example="1"),
     *             @SWG\Property(property="value", type="string", description="管理员FLAG 键值对", example="众测管理员")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionMapFlags()
    {
        $flags = $this->actionFlags();
        $result = [];
        foreach ($flags as $key => $value) {
            $result[] = compact('key', 'value');
        }
        return $result;
    }

    /**
     * 删除管理员
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/manager/{id}",
     *     tags={"Admin - Manager"},
     *     description="删除管理员",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="管理员不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = $this->findAdmin($id);
        if (!$model->delete()) {
            throw new Exception($model->getFirstErrors(), '删除失败');
        }
    }

    /**
     * 修改管理范围
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/manager/{id}",
     *     tags={"Admin - Manager"},
     *     description="更新",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminManagerCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminManager")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="管理员不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        try {
            $model = $this->findAdmin($id);
        } catch (NotFoundHttpException $e) {
            $identity = Identity::findOne($id);
            if (!$identity) {
                throw new NotFoundHttpException('没有这个用户');
            }
            $model = Admin::loadFor($id);
        }
        $this->setAttributes($model);
        if ($model->save()) {
            return $this->Format($model);
        } else {
            throw new Exception($model->getFirstErrors(), '操作失败');
        }
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminManager",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="用户id", example=1),
     *     @SWG\Property(property="user", ref="#/definitions/UserBasic"),
     *     @SWG\Property(property="flags", ref="#/definitions/Flags"),
     *     @SWG\Property(property="isSuper", type="boolean",  description="是否为超级管理，仅限 root"),
     *     @SWG\Property(property="dayLeft", type="string", description="任期天数", example="N天"),
     *     @SWG\Property(property="summary", type="string", description="摘要", example="超级管理"),
     * )
     */
    private function Format($model)
    {
        $result = [
            'id' => $model->id,
            'user' => FormatIdentity::basic($model->identity),
            'dayLeft' => $model->dayLeft,
            'summary' => $model->summary,
            'isSuper' => $model->isSuper,
            'flags' => $model->flags,
        ];
        return $result;
    }

    /**
     * 表单载入模型
     * @param Admin $model
     *
     * @SWG\Definition(
     *     definition="AdminManagerCreate",
     *     type="object",
     *     @SWG\Property(property="flags", ref="#/definitions/Flags"),
     *     @SWG\Property(property="isSuper", type="boolean", description="是否为超级管理，仅限 root"),
     *     @SWG\Property(property="dayLeft", type="integer", description="任期剩余天数", example=30)
     * )
     */
    private function setAttributes($model)
    {
        $params = Yii::$app->request->bodyParams;
        $model->attributes = $params;
    }

    /**
     * 查找管理员
     * @param int $id 用户ID
     * @throws \yii\web\HttpException
     * @return Admin
     */
    private function findAdmin($id)
    {
        $model = Admin::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('这个管理员不存在');
        } else {
            return $model;
        }
    }
}
