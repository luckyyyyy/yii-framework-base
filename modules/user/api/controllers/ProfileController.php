<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\user\api\controllers;

use app\components\PhoneValidator;
use app\models\Identity;
use app\models\Address;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use Yii;
use yii\web\UnauthorizedHttpException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\db\IntegrityException;

/**
 * 用户资料
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="User", description="用户相关")
 */
class ProfileController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET profile' => 'view',
            'GET peek/<id:\d+>' => 'peek',
            'PUT profile' => 'update',
            'PUT profile/reset' => 'reset-password',
            'PATCH profile/bind-phone' => 'bind-phone',
            'GET profile/address' => 'get-address',
            'PUT profile/address' => 'edit-address',
            'POST profile/check-name' => 'check-name'
        ];
    }

    /**
     * 查看个人资料
     * @param string $strict
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Get(path="/user/profile",
     *     tags={"User"},
     *     description="获取当前用户自己的完整个人资料。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(type="object",
     *              allOf={
     *                  @SWG\Schema(ref="#/definitions/UserBasic"),
     *                  @SWG\Schema(ref="#/definitions/UserPerms"),
     *              }
     *          )
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionView()
    {
        $model = Yii::$app->user->identity;
        if ($model === null) {
            throw new UnauthorizedHttpException('Your request was made with invalid credentials.');
        } else {
            return array_merge(FormatIdentity::basic($model), FormatIdentity::perms($model));
        }
    }

    /**
     * 查看其他人的个人基础信息
     * @param string $strict
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Get(path="/user/peek/{id}",
     *     tags={"User"},
     *     description="查看其他人的个人基础信息",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="path", name="id", type="string", description="用户ID", required=true, default="1"),
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(ref="#/definitions/UserBasic")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionPeek($id)
    {
        $model = Identity::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('用户不存在');
        } else {
            return FormatIdentity::basic($model);
        }
    }

    /**
     * 更新个人资料
     * @return array
     *
     * @SWG\Put(path="/user/profile",
     *     tags={"User"},
     *     description="更新用户的信息，不更新的部分不需要传。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="name", type="boolean", description="名字", example="姓名"),
     *             @SWG\Property(property="gender", type="integer", description="性别", example=1),
     *             @SWG\Property(property="avatar", type="integer", description="头像地址", example=1),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserBasic"),
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionUpdate()
    {
        $identity = Yii::$app->user->identity;
        $params = Yii::$app->request->bodyParams;
        $identity->attributes = $params;
        if ($identity->save()) {
            return array_merge(FormatIdentity::basic($identity), FormatIdentity::perms($identity));
        } else {
            throw new Exception($identity->getFirstErrors(), current($identity->getFirstErrors()));
        }
    }

    /**
     * 重置密码
     *
     * @throws BadRequestHttpException
     *
     * @SWG\Put(path="/user/profile/reset",
     *     tags={"User"},
     *     description="重置密码",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="password", type="string", description="密码", example="888888"),
     *             @SWG\Property(property="phone", type="integer", description="手机号", example="1300000000"),
     *             @SWG\Property(property="code", type="integer", description="验证码", example="121212"),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserBasic"),
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionResetPassword()
    {
        $params = Yii::$app->request->bodyParams;
        if (!isset($params['phone']) || !trim($params['phone'])) {
            throw new BadRequestHttpException('手机号未提交');
        }
        if (!isset($params['password']) || !trim($params['password'])) {
            throw new BadRequestHttpException('密码未提交');
        }
        if (!isset($params['code']) || !trim($params['code'])) {
            throw new BadRequestHttpException('验证码未提交');
        }
        $phone = $params['phone'];
        $code = $params['code'];
        $identity = Identity::findOne(['phone' => $phone]);
        if ($identity) {
            $validator = new PhoneValidator(['phoneValue' => $phone]);
            if (!$validator->validate($code)) {
                throw new BadRequestHttpException('手机号或验证码错误');
            }
            $identity->setScenario(Identity::SCENARIO_RESET);
            $identity->attributes = $params;
            if ($identity->save()) {
                return array_merge(FormatIdentity::basic($identity), FormatIdentity::perms($identity));
            } else {
                throw new Exception($identity->getFirstErrors(), current($identity->getFirstErrors()));
            }
        } else {
            throw new BadRequestHttpException('手机号错误');
        }


    }

    /**
     * 绑定手机号
     * @return array
     *
     * @SWG\Patch(path="/user/profile/bind-phone",
     *     tags={"User"},
     *     description="绑定手机号",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="phone", type="integer", description="性别", example=13333333333),
     *             @SWG\Property(property="code", type="integer", description="头像地址", example=123456),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *          @SWG\Schema(type="object",
     *              allOf={
     *                  @SWG\Schema(ref="#/definitions/UserBasic"),
     *                  @SWG\Schema(ref="#/definitions/UserPerms"),
     *              }
     *          )
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionBindPhone()
    {
        $params = Yii::$app->request->bodyParams;
        if (isset($params['phone']) && isset($params['code'])) {
            $validator = new PhoneValidator(['phoneValue' => $params['phone']]);
            if (!$validator->validate($params['code'])) {
                throw new BadRequestHttpException('您输入的验证码不正确');
            }
            $identity = Yii::$app->user->identity;
            if ($identity->phone) {
                throw new BadRequestHttpException('您已经绑定过手机号了');
            }
            $identity->phone = $params['phone'];
            try {
                $identity->save();
            } catch (IntegrityException $e) {
                throw new BadRequestHttpException('该号码已经绑定了其他用户。');
            }
            return $this->actionView();
        } else {
            throw new BadRequestHttpException();
        }
    }

    /**
     * 获取当前用户地址
     * @return array
     *
     * @SWG\Get(path="/user/profile/address",
     *     tags={"User"},
     *     description="获取当前用户地址",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserAddress")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     * )
     */
    public function actionGetAddress()
    {
        $identity = Yii::$app->user->identity;
        return current(FormatIdentity::address($identity));
    }

    /**
     * 修改用户地址
     * @return array
     *
     * @SWG\Put(path="/user/profile/address",
     *     tags={"User"},
     *     description="修改用户地址信息",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="city", type="string", description="城市省份", example="浙江 杭州"),
     *             @SWG\Property(property="name", type="string", description="姓名", example="陈老板"),
     *             @SWG\Property(property="address", type="string", description="详细地址", example="阿里巴巴西溪园区 4号楼 报告厅"),
     *             @SWG\Property(property="phone", type="string", description="电话号码", example="13800000000"),
     *         )
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/UserAddress")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=400, description="地址修改失败"),
     * )
     */
    public function actionEditAddress()
    {
        $params = Yii::$app->request->bodyParams;
        $identity = Yii::$app->user->identity;
        $address = Address::loadFor($identity);
        $address->attributes = $params;
        if ($address->save()) {
            return $this->actionGetAddress();
        } else {
            throw new BadRequestHttpException('地址修改失败');
        }
    }

    /**
     * 检查名字是否可用
     * @param string $name
     * @return array
     * @throws BadRequestHttpException
     *
     * @SWG\Post(path="/user/profile/check-name",
     *     tags={"User"},
     *     summary="检测昵称可用",
     *     description="在保存设置前检测昵称是否可用。",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="formData", name="name", type="string", description="要检查的昵称", required=true),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=406, description="不可用")
     * )
     */
    public function actionCheckName()
    {
        $name = trim(Yii::$app->request->post('name'));
        if (empty($name)) {
            throw new BadRequestHttpException('昵称不能为空');
        }
        $model = new Identity([
            'id' => Yii::$app->user->id,
            'name' => $name,
        ]);
        if (!$model->validate(['name'])) {
            throw new Exception($model->getFirstErrors(), $model->getFirstError('name'), 40600);
        }
    }
}
