<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\api\controllers;

use app\modules\user\api\controllers\FormatIdentity;
use app\modules\api\MetaResponse;
use app\modules\api\Exception;
use app\models\Cron;
use Yii;
use yii\web\NotFoundHttpException;
use yii\db\IntegrityException;
use yii\filters\AccessControl;

/**
 * 管理后台 定时任务相关
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Cron", description="管理员 - 定时任务")
 *
 */
class CronController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE cron/<id:\d+>' => 'delete',
            'PUT cron/<id:\d+>' => 'update',
            'POST cron' => 'create',
            'GET cron' => 'all',
            'GET cron/log/<id:\d+>' => 'log',
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
                'roles' => ['*'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * 新增定时任务
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/cron",
     *     tags={"Admin - Cron"},
     *     description="新增定时任务",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminCronCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminCron")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionCreate()
    {
        $model = new Cron();
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                $errors = $model->getFirstErrors();
                throw new Exception($errors, '添加失败 ' . current($errors));
            } else {
                return $this->format($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '创建失败');
        }
    }

    /**
     * 修改定时任务
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/cron/{id}",
     *     tags={"Admin - Cron"},
     *     description="更新定时任务",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminCronCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminCron")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="定时任务不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->status !== Cron::STATUS_WAIT) {
            throw new \yii\web\BadRequestHttpException('不是等待中的定时任务无法修改');
        }
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '修改失败');
            } else {
                return $this->format($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '修改失败' . $e->getName());
        }
    }

    /**
     * 删除定时任务
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/cron/{id}",
     *     tags={"Admin - Cron"},
     *     description="删除一个定时任务",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="定时任务不存在"),
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
     * 返回队列日志
     * @return MetaResponse
     * @throws \yii\web\HttpException
     *
     * @SWG\Get(path="/admin/cron/log/{id}",
     *     tags={"Admin - Cron"},
     *     description="警告：此接口直接输出日志内容，不遵循API规范。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="path", name="id", type="string", description="定时任务", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=404, description="众测不存在"),
     * )
     */
    public function actionLog($id)
    {
        $cron = $this->findModel($id);
        $path = $cron->logPath;
        if (file_exists($path)) {
            readfile($path);
        } else {
            echo '暂无定时任务日志';
        }
        exit(0);
    }

    /**
     * 返回定时任务列表
     * @return array
     *
     * @SWG\Get(path="/admin/cron",
     *     tags={"Admin - Cron"},
     *     description="返回定时任务列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Parameter(in="query", name="q", type="string", description="摘要搜索"),
     *     @SWG\Parameter(in="query", name="status", type="integer", description="状态搜索"),
     *     @SWG\Parameter(in="query", name="type", type="integer", description="类型搜索"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminCron")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionAll()
    {
        $query = Cron::find()->with('identity');
        $params = Yii::$app->request->get();
        foreach (['q' => 'summary', 'type' => 'type', 'status' => 'status'] as $k => $v) {
            // @fixme > 0
            if (!empty($params[$k])) {
                $query->andWhere([$v => $params[$k]]);
            }
        }
        $all = $query->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 格式化输出
     * @param Cron $model
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminCron",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="status", type="integer", description="当前状态 1=任务等待中,2=任务运行中,3=任务已完成,4=任务呗挂起", example=1),
     *     @SWG\Property(property="type", type="integer", description="具体问我太多了", example=1),
     *     @SWG\Property(property="flags", description="标记 具体问我", ref="#/definitions/Flags"),
     *     @SWG\Property(property="time_exec", type="integer", description="开始执行时间", example=1),
     *     @SWG\Property(property="time_loop", type="integer", description="周期时间不是周期就显示0，单位秒", example=3600),
     *     @SWG\Property(property="extra", type="object", description="前端配置的扩展字段", example={"id":1}),
     *     @SWG\Property(property="user", description="创建者用户信息", ref="#/definitions/UserBasic"),
     * )
     */
    private function format($model)
    {
        return [
            'id' => $model->id,
            'status' => $model->status,
            'type' => $model->type,
            'flags' => $model->flags,
            'time_exec' => date('Y/m/d H:i', $model->time_exec),
            'time_loop' => $model->time_loop / 60,
            'extra' => $model->extra,
            'user' => FormatIdentity::basic($model->identity)
        ];
    }

    /**
     * 表单载入模型
     * @param Cron $model
     * @SWG\Definition(
     *     definition="AdminCronCreate",
     *     type="object",
     *     @SWG\Property(property="type", type="integer", description="具体问我太多了", example=1),
     *     @SWG\Property(property="time_exec", type="integer", description="开始执行时间", example=1),
     *     @SWG\Property(property="time_loop", type="integer", description="周期时间不是周期就显示0，单位秒", example=3600),
     *     @SWG\Property(property="extra", type="object", description="前端配置的扩展字段", example={"id":1}),
     *     @SWG\Property(property="summary", type="string", description="周期时间不是周期就显示0", example="测试任务"),
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
     * @return Cron
     */
    private function findModel($id)
    {
        $model = Cron::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('定时任务不存在');
        } else {
            return $model;
        }
    }
}
