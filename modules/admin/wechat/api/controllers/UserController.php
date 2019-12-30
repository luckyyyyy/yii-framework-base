<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\wechat\api\controllers;

use app\components\WechatApp;
use app\models\PageQuery;
use app\models\wechat\UserTags;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\models\wechat\User;
use app\models\wechat\WechatMedia;
use app\modules\wechat\models\WechatUserSyncTask;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;

/**
 * 管理后台 微信用户控制器
 *
 * @author William Chan <root@williamchan.me>
 *
 */
class UserController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET user/<scenario:[\w-]+>' => 'all',
            'POST user/send-customer/<scenario:[\w-]+>/<id:\d+>' => 'send-customer',
            'GET tags/<scenario:[\w-]+>' => 'tags',
            'GET user/fetch-last-task/<scenario:[\w-]+>' => 'fetch-last-task',
            'PUT user/fetch-user-retry/<id:\d+>' => 'fetch-user-retry',
            'POST user/fetch-user/<scenario:[\w-]+>' => 'fetch-user',
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
     * 获取微信用户列表
     * @param $scenario
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/wechat/user/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="返回微信用户",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Parameter(in="query", name="name", type="string", description="名字查询或者id"),
     *     @SWG\Parameter(in="query", name="startTime", type="string", description="关注开始时间"),
     *     @SWG\Parameter(in="query", name="endTime", type="string", description="关注结束时间"),
     *     @SWG\Parameter(in="query", name="isSubscribe", type="string", description="是否关注 all:不限 y:关注 n:不关注", enum={"all","y","n"}, default="all"),
     *     @SWG\Parameter(in="query", name="tag_id", type="integer", description="标签id"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatUser")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionAll($scenario)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        /** @var PageQuery $query */
        $query = User::find();
        $name = Yii::$app->request->get('name');
        if ($name) {
            $query->joinWith('identity');
            $query->where(['identity.name' => $name]);
            if (is_numeric($name)) {
                $query->orWhere(['identity.id' => (int)$name]);
                $query->orWhere(['identity.phone' => $name]);
            }
        } else {
            $query->with('identity');
        }
        $startTime = Yii::$app->request->getQueryParam('startTime', null);
        $endTime = Yii::$app->request->getQueryParam('endTime', null);
        $isSubscribe = Yii::$app->request->getQueryParam('isSubscribe', 'all');
        $tagId = Yii::$app->request->getQueryParam('tag_id', false);
        if ($isSubscribe != 'all') {
            $query->andWhere(['=', User::tableName() . '.is_follow', $isSubscribe == 'y' ? 'Y' : 'N']);
        }
        if ($tagId) {
            $query->joinWith('tags')->andWhere(['=', UserTags::tableName() . '.tag_id', $tagId])->andWhere(['=', UserTags::tableName() . '.scenario', $scenario]);
        }

        /** @var PageQuery $cancelQuery */
        $cancelQuery = User::find()->distinct()
            ->select('id')
            ->andWhere(['>', User::tableName() . '.time_cancel', 0])
            ->andWhere(['=', User::tableName() . '.is_follow', 'N']);
        /** @var PageQuery $subscribeQuery */
        $subscribeQuery = User::find()->distinct()
            ->select('id')
            ->andWhere(['=', User::tableName() . '.is_follow', 'Y']);
        if ($startTime) {
            $query->andWhere(['>=', User::tableName() . '.time_subscribe', strtotime($startTime)]);
            $subscribeQuery->andWhere(['>=', User::tableName() . '.time_subscribe', strtotime($startTime)]);
            $cancelQuery->andWhere(['>=', User::tableName() . '.time_cancel', strtotime($startTime)]);
        }
        if ($endTime) {
            $query->andWhere(['<=', User::tableName() . '.time_subscribe', strtotime($endTime)]);
            $subscribeQuery->andWhere(['<=', User::tableName() . '.time_subscribe', strtotime($endTime)]);
            $cancelQuery->andWhere(['<=', User::tableName() . '.time_cancel', strtotime($endTime)]);
        }

        $all = $query->findByOffset(SORT_DESC, 'id', false);
        $result = [];
        /** @var User $item */
        foreach ($all['result'] as $item) {
            if ($item->identity) {
                $result[] = Format::user($item);
            }
        }

        $extra = ArrayHelper::merge($all['extra'], [
            'cancel' => $cancelQuery->count(),
            'subscribe' => $subscribeQuery->count(),
        ]);

        return new MetaResponse($result, ['extra' => $extra]);
    }

    /**
     * 对这个用户发送客服消息
     *
     * @param $scenario
     * @param $id
     * @return bool
     * @throws BadRequestHttpException
     *
     * @SWG\Post(path="/admin/wechat/user/send-customer/{scenario}/{id}",
     *     tags={"Admin - Wechat"},
     *     description="这个接口只用于单独下发给这个用户一条客服消息，一般用于测试或单独触达。",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="integer", description="用户id", required=true, default=1),
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             @SWG\Property(property="media_id", type="integer", description="媒体id", example=1),
     *             @SWG\Property(property="message", type="string", description="文本内容和媒体id二选一", example="Hello"),
     *         )
     *     ),
     *     @SWG\Response(response=404, description="用户或者媒体不存在"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionSendCustomer($scenario, $id)
    {
        $wechat = Yii::$app->wechatApp->setScenario($scenario);
        $params = Yii::$app->request->bodyParams;
        if (isset($params['media_id']) || isset($params['message'])) {
            $user = User::findOne($id);
            if ($user) {
                $customer = [$user->openid];
                if (!empty($params['media_id'])) {
                    $media = WechatMedia::findOne($params['media_id']);
                    if ($media) {
                        $customer[] = $media->wechatCustomerFormat;
                        $customer[] = $media->wechatCustomerType;
                    } else {
                        throw new BadRequestHttpException('媒体资源不存在');
                    }
                } elseif (!empty($params['message'])) {
                    $customer[] = ['content' => (string)$params['message']];
                    $customer[] = 'text';
                } else {
                    throw new BadRequestHttpException('缺少必要参数');
                }
                $customer[] = true;
                // 不使用队列发送
                try {
                    call_user_func_array([$wechat, 'sendCustomer'], $customer);
                    return true;
                } catch (\Exception $e) {
                    throw new BadRequestHttpException($e->getMessage());
                }
            }
        }
        throw new BadRequestHttpException('缺少必要参数或用户不存在');
    }

    /**
     * 创建拉取用户的任务
     *
     * @param $scenario
     * @throws Exception
     *
     * @SWG\Post(path="/admin/wechat/user/fetch-user/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="创建拉取用户的任务",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Response(response=404, description="用户或者媒体不存在"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionFetchUser($scenario)
    {
        if ($model = WechatUserSyncTask::findLastTask($scenario)) {
            if (!$model->isFinish()) {
                throw new Exception('', '已经有执行中的任务且没有完成');
            }
        }

        $model = new WechatUserSyncTask([
            'scenario' => $scenario
        ]);

        if (!$model->save()) {
            throw new Exception('', '任务创建失败');
        }
    }

    /**
     * 重试拉取用户的任务
     *
     * @param $id
     * @throws Exception
     * @throws NotFoundHttpException
     *
     * @SWG\Put(path="/admin/wechat/user/fetch-user-retry/{id}",
     *     tags={"Admin - Wechat"},
     *     description="重试拉取用户的任务",
     *     @SWG\Parameter(in="path", name="id", type="integer", description="任务id", required=true, default=1),
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Response(response=404, description="用户或者媒体不存在"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionFetchUserRetry($id)
    {
        if (!$model = WechatUserSyncTask::findOne($id)) {
            throw new NotFoundHttpException('任务不存在');
        }
        if ($model->status != WechatUserSyncTask::STATUS_FAILED) {
            throw new Exception('', '该任务不能重试');
        }

        $model->status = WechatUserSyncTask::STATUS_READY;
        if (!$model->save()) {
            throw new Exception('', '任务更新失败');
        }
    }

    /**
     * 获取最近拉取用户的信息
     *
     * @param $scenario
     * @return WechatUserSyncTask|array|\yii\db\ActiveRecord|null
     *
     * @SWG\Get(path="/admin/wechat/user/fetch-last-task/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="获取最近拉取用户的信息",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Response(response=404, description="用户或者媒体不存在"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionFetchLastTask($scenario)
    {
        return WechatUserSyncTask::findLastTask($scenario);
    }
}
