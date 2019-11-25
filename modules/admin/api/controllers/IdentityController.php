<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\api\controllers;

use app\components\Html;
use app\models\Identity;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\modules\user\api\controllers\FormatIdentity;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\AccessControl;

/**
 * 管理员 用户通行证相关
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Identity", description="管理员 - 通行证相关")
 */
class IdentityController extends \app\modules\api\controllers\Controller
{
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET identity' => 'index',
            'GET identity/<id:\d+>' => 'profile',
            'PUT identity/<id:\d+>' => 'profile-edit',
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
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['*'],
                ],
                [
                    'actions' => ['profile-edit'],
                    'allow' => false,
                    'roles' => ['%%'],
                ],
                [
                    'allow' => true,
                    'roles' => ['%%'],
                ],
            ],
        ];
        return $behaviors;
    }

    /**
     * 获取通行证列表
     * @return MetaResponse
     *
     * @SWG\Get(path="/admin/identity",
     *     tags={"Admin - Identity"},
     *     description="返回通行证列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Parameter(in="query", name="name", type="string", description="名字查询或者id", default="青阳魂"),
     *     @SWG\Parameter(in="query", name="type", type="string", enum={"all", "mock"}, description="类型(运营号)", default="all"),
     *
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(allOf={
     *             @SWG\Schema(ref="#/definitions/UserBasic"),
     *             @SWG\Schema(ref="#/definitions/UserPerms"),
     *         }),
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     *
     */
    public function actionIndex()
    {
        $query = Identity::find()->with('admin');
        $name = Yii::$app->request->get('name');
        $type = Yii::$app->request->get('type', 'all');

        if ($name) {
            $query->where(['name' => $name]);
            if (is_numeric($name)) {
                $query->orWhere(['id' => $name]);
            }
        }

        if ($type === 'mock') {
            $all = $query->andWhere(['&', 'flag', Identity::FLAG_MOCK])->all();
            $result = [];
            foreach ($all as $item) {
                $result[] = array_merge(FormatIdentity::basic($item), FormatIdentity::perms($item));
            }
            return $result;
        } else {
            $all = $query->findByOffset(SORT_ASC);
            $result = [];
            foreach ($all['result'] as $item) {
                $result[] = array_merge(FormatIdentity::basic($item), FormatIdentity::perms($item));
            }
            return new MetaResponse($result, ['extra' => $all['extra']]);
        }
    }

    /**
     * 查看一个用户的详细信息
     * @param int $id
     * @throws NotFoundHttpException;
     * @return array
     *
     * @SWG\Get(path="/admin/identity/{id}",
     *     tags={"Admin - Identity"},
     *     description="查看一个用户的详细信息",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserFull")
     *     ),
     *     @SWG\Response(response=404, description="用户不存在"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=403, description="权限不足"),
     * )
     */
    public function actionProfile($id)
    {
        $identity = Identity::findOne($id);
        if ($identity) {
            return FormatIdentity::full($identity);
        } else {
            throw new NotFoundHttpException('没有这个用户');
        }
    }

    /**
     * 修改用户信息（超级管理员权限）
     * @param int $id
     * @return array
     *
     * @SWG\Put(path="/admin/identity/{id}",
     *     tags={"Admin - Identity"},
     *     description="更新用户的信息，不更新的部分不需要传。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="name", type="boolean", description="名字", example="姓名"),
     *             @SWG\Property(property="password", type="string", description="用户密码", example="test"),
     *             @SWG\Property(property="point", type="integer", description="用户总积分", example=100),
     *             @SWG\Property(property="phone", type="integer", description="用户绑定手机号", example=1300000000),
     *             @SWG\Property(property="gender", type="integer", description="性别", example=1),
     *             @SWG\Property(property="avatar", type="integer", description="头像地址", example=1),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserFull")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionProfileEdit($id)
    {
        $user = Yii::$app->user;
        $identity = Identity::findOne($id);
        if ($identity) {
            if ($identity->isRoot && !$user->isRoot) {
                throw new ForbiddenHttpException('您无权修改Root用户信息');
            }
            $params = Yii::$app->request->bodyParams;
            $identity->setScenario(Identity::SCENARIO_ADMIN);
            $identity->attributes = $params;
            if ($identity->save()) {
                return FormatIdentity::full($identity);
            } else {
                throw new Exception($identity->getFirstErrors(), current($identity->getFirstErrors()));
            }
        } else {
            throw new NotFoundHttpException('没有这个用户');
        }
    }
}
