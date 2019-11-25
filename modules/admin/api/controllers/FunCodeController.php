<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\modules\admin\api\controllers;

use app\models\FunCode;

use app\models\FunCodeLog;
use app\models\TurnPageTrait;
use app\modules\api\controllers\Controller;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use yii\db\IntegrityException;
use yii\filters\AccessControl;
use Yii;
use yii\web\NotFoundHttpException;

/**
 * Admin Fun Code
 *
 * @author fangjiali <root@fangjiali>
 * @SWG\Tag(name="Admin - Fun - Code", description="激活码")
 */
class FunCodeController extends Controller
{
    use TurnPageTrait;
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE funcode/<id:[\w-]+>' => 'delete',
            'POST funcode' => 'create',
            'PUT funcode/<id:[\w-]+>' => 'update',
            'GET funcode' => 'index',
            'GET logs' => 'logs',
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
     * 获取激活码列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/funcode",
     *     tags={"Admin - Fun - Code"},
     *     description="获取激活码列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(ref="#/definitions/FundCodeOne")),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex()
    {
        $model = FunCode::find();
        $all = $this->getPages($model, 'time_create');
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->format($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 创建激活码
     *
     * @SWG\Definition(
     *     definition="FundCodeCreate",
     *     type="object",
     *     @SWG\Property(property="bind_id", type="string", description="绑定使用者id,0表示不限制使用的用户", example="1"),
     *     @SWG\Property(property="isUnlimit", type="bool", description="是否是无限次数", example=false),
     *     @SWG\Property(property="feature", type="bool", description="功能特性", example="testing-point"),
     *     @SWG\Property(property="count", type="integer", description="可以使用的次数（无限次数不受此影响）", example="2"),
     * )
     *
     * @SWG\Post(path="/admin/funcode",
     *     tags={"Admin - Fun - Code"},
     *     description="创建短网址",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/FundCodeCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/FundCodeOne")
     *     ),
     *     @SWG\Response(response=404, description="激活码不存在"),
     * )
     */
    public function actionCreate($id = null)
    {
        $params = Yii::$app->request->getBodyParams();
        if ($id === null) {
            $model = new FunCode();
        } else {
            $model = FunCode::findOne($id);
            if (!$model) {
                throw new NotFoundHttpException("激活码不存在");
            }
        }
        try {
            $model->scenario = FunCode::SCENARIO_DEFAULT;
            $model->attributes = $params;
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '操作失败');
            } else {
                return $this->Format($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getMessage(), '操作失败 ' . $e->getName());
        }
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="FundCodeOne",
     *     type="object",
     *     @SWG\Property(property="id", type="string", description="激活码ID", example=1),
     *     @SWG\Property(property="bind", type="obj", description="绑定使用者id", example={}),
     *     @SWG\Property(property="isUnlimit", type="bool", description="是否是无限次数", example=false),
     *     @SWG\Property(property="feature", type="bool", description="功能特性", example="testing-point"),
     *     @SWG\Property(property="define", description="功能", type="object",
     *         @SWG\Property(property="label", type="object", description="define", example="增加众测抽奖机会"),
     *         @SWG\Property(property="description", type="object", description="define", example="增加最新一期众测的抽奖机会，随机1-5次"),
     *     ),
     *     @SWG\Property(property="count", type="integer", description="可以使用的次数（无限次数不受此影响）", example="2"),
     *     @SWG\Property(property="time_create", type="bool", description="创建时间", example="2018-05-04 06:31:09"),
     *     @SWG\Property(property="time_expire", type="bool", description="过期时间", example="2018-05-04 06:31:09"),
     * )
     */
    private function format(FunCode $model)
    {
        $result = [
            'id' => $model->id,
            'bind' => $model->bind,
            'isUnlimit' => $model->isUnlimit,
            'feature' => $model->feature,
            'define' => $model->define,
            'count' => $model->count,
            'isUsed' => $model->isUsed,
            'time_create' => date('Y-m-d H:i:s', $model->time_create),
            'time_expire' => date('Y-m-d H:i:s', $model->time_expire),
        ];
        return $result;
    }


    /**
     * 修改激活码
     * @return array
     *
     * @SWG\Put(path="/admin/funcode/{id}",
     *     tags={"Admin - Fun - Code"},
     *     description="修改激活码",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="path", name="id", type="string", description="激活码ID", required=true, default="testing-point_0oJ09WQevHIbd1HI"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/FundCodeCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/FundCodeOne")
     *     ),
     *     @SWG\Response(response=404, description="激活码不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        return $this->actionCreate($id);
    }

    /**
     * 所有使用日志
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/logs",
     *     tags={"Admin - Fun - Code"},
     *     description="所有使用日志",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *     @SWG\Schema(ref="#/definitions/FundCodeLogOne")),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionLogs()
    {
        $model = FunCodeLog::find();
        $all = $this->getPages($model, 'time_use');
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = $this->logFormat($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 使用日志格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="FundCodeLogOne",
     *     type="object",
     *     @SWG\Property(property="id", type="string", description="激活码ID", example=1),
     *     @SWG\Property(property="ip", type="string", description="客户端ip", example="192.168.10.33"),
     *     @SWG\Property(property="orig_data", type="string", description="原数据，使用时记录", example=""),
     *     @SWG\Property(property="identity", type="string", description="使用者", example={}),
     *     @SWG\Property(property="time_use", type="bool", description="使用时间", example="2018-05-04 06:31:09"),
     * )
     */
    private function logFormat($model)
    {
        $result = [
            'id' => $model->id,
            'ip' => $model->ip,
            'orig_data' => $model->orig_data,
            'identity' => $model->identity,
            'time_use' => date('Y-m-d H:i:s', $model->time_use),
        ];
        return $result;
    }
}
