<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\api\controllers;

use app\models\AdminBehaviorLog;
use app\models\AdminLoginLog;
use app\modules\api\MetaResponse;
use app\modules\user\api\controllers\FormatIdentity;
use Yii;
use yii\filters\AccessControl;

/**
 * 管理员 Log
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Log", description="管理员 - 日志")
 */
class LogController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET log/behavior' => 'behavior-index',
            'GET log/login' => 'login-index',
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
     * 获取管理员行为日志
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/log/behavior",
     *     tags={"Admin - Log"},
     *     description="返回管理员行为日志",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="id", example=1),
     *                 @SWG\Property(property="url", type="string", description="文件名", example="api/admin/xxx"),
     *                 @SWG\Property(property="method", type="integer", description="method", example="GET"),
     *                 @SWG\Property(property="status", type="integer", description="请求的结果状态", example=200),
     *                 @SWG\Property(property="states", type="object", description="提交的数据", example={"key":"value"}),
     *                 @SWG\Property(property="ip", type="string", description="IP", example="127.0.0.1"),
     *                 @SWG\Property(property="time", type="string", description="时间", example="2017-01-01 11:11:11"),
     *                 @SWG\Property(property="agent", type="string", description="user agent", example="Mozilla/5.0"),
     *                 @SWG\Property(property="user",
     *                     @SWG\Schema(type="object",
     *                         allOf={
     *                             @SWG\Schema(ref="#/definitions/UserBasic"),
     *                             @SWG\Schema(ref="#/definitions/UserPerms"),
     *                         }
     *                     )
     *                 ),
     *             )
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionBehaviorIndex()
    {
        $all = AdminBehaviorLog::find()->with(['identity', 'identity.admin'])->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = [
                'id' => $item->id,
                'user' => array_merge(FormatIdentity::basic($item->identity), FormatIdentity::perms($item->identity)),
                'url' => $item->url,
                'method' => $item->method,
                'status' => $item->status,
                'states' => $item->states,
                'ip' => $item->ip,
                'agent' => $item->agent,
                'time' => date('Y-m-d H:i:s', $item->time),
            ];
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 管理员登陆日志
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/log/login",
     *     tags={"Admin - Log"},
     *     description="返回管理员登陆日志",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(
     *                 type="object",
     *                 @SWG\Property(property="id", type="integer", description="id", example=1),
     *                 @SWG\Property(property="method", type="integer", description="method", example="wechat"),
     *                 @SWG\Property(property="states", type="object", description="登陆数据", example={"key":"value"}),
     *                 @SWG\Property(property="ip", type="string", description="IP", example="127.0.0.1"),
     *                 @SWG\Property(property="time", type="string", description="时间", example="2017-01-01 11:11:11"),
     *                 @SWG\Property(property="agent", type="string", description="user agent", example="Mozilla/5.0"),
     *                 @SWG\Property(property="user", ref="#/definitions/UserBasic"),
     *             )
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionLoginIndex()
    {
        $all = AdminLoginLog::find()->with(['identity', 'identity.admin'])->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = [
                'id' => $item->id,
                'user' => array_merge(FormatIdentity::basic($item->identity), FormatIdentity::perms($item->identity)),
                'method' => $item->method,
                'states' => $item->states,
                'ip' => $item->ip,
                'agent' => $item->agent,
                'time' => date('Y-m-d H:i:s', $item->time),
            ];
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }
}
