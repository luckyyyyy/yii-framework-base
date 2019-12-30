<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\wechat\api\controllers;

use app\modules\api\MetaResponse;
use app\modules\api\Exception;
use app\models\wechat\WechatQrcode;
use Yii;
use yii\web\NotFoundHttpException;
use yii\db\IntegrityException;
use yii\filters\AccessControl;

/**
 * 管理后台 微信关键词控制器
 *
 * @author William Chan <root@williamchan.me>
 *
 * @SWG\Definition(
 *     definition="AdminWechatQrcodeCreate",
 *     type="object",
 *     @SWG\Property(property="scene", type="string", enum={"WECHAT_MEDIA"}, description="使用场景", default="WECHAT_MEDIA")),
 *     @SWG\Property(property="media_id", type="integer", description="媒体ID", example=5),
 *     @SWG\Property(property="expire", type="integer", description="有效期 -1是永久", example=-1),
 *     @SWG\Property(property="summary", type="string", description="摘要 搜索用 可以不写", example="13456"),
 * )
 */
class QrcodeController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE qrcode/<scenario:[\w-]+>/<id:\d+>' => 'delete',
            'PUT qrcode/<scenario:[\w-]+>/<id:\d+>' => 'update',
            'POST qrcode/<scenario:[\w-]+>' => 'create',
            'GET qrcode/<scenario:[\w-]+>' => 'all',
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
     * 新增二维码
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/wechat/qrcode/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="新增二维码",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatQrcodeCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatQrcode")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionCreate($scenario)
    {
        // TODO 目前暂不支持其他情况 必须填写media_id以及summary
        Yii::$app->wechatApp->setScenario($scenario);
        $data = Yii::$app->request->bodyParams;
        if (!empty($data['summary'])) {
            $data['scene'] = 'WECHAT_MEDIA_';
            $model = WechatQrcode::loadFor($data);
            return Format::qrcode($model);
        }
        throw new \yii\web\BadRequestHttpException('请填写media_id以及summary');
    }

    /**
     * 修改二维码
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/wechat/qrcode/{scenario}/{id}",
     *     tags={"Admin - Wechat"},
     *     description="修改二维码",
     *     security={{"api_key": {}}},
     *     deprecated=true,
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatQrcodeCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatQrcode")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionUpdate($scenario, $id)
    {
        // TODO 待实现
    }

    /**
     * 删除二维码
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/wechat/qrcode/{scenario}/{id}",
     *     tags={"Admin - Wechat"},
     *     description="删除二维码",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="二维码不存在"),
     * )
     */
    public function actionDelete($scenario, $id)
    {
        Yii::$app->wechatApp->setScenario($scenario);
        $model = WechatQrcode::findOne($id);
        if (!$model || !$model->delete()) {
            throw new NotFoundHttpException('二维码不存在');
        }
    }

    /**
     * 获取二维码列表
     * @return array
     *
     * @SWG\Get(path="/admin/wechat/qrcode/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="返回 scenario 公众号的二维码列表",
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
        $query = WechatQrcode::find();
        $q = Yii::$app->request->get('q');
        if (!empty($q)) {
            $query->andWhere(['like', 'summary', $q]);
        }
        $all = $query->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = Format::qrcode($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }
}
