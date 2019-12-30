<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\wechat\api\controllers;

use app\modules\api\MetaResponse;
use app\modules\api\Exception;
use app\models\wechat\WechatMedia;
use Yii;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use yii\db\IntegrityException;

/**
 * 管理后台 微信资源库控制器
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin - Wechat", description="管理员 - 微信相关")
 *
 */
class MediaController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'DELETE media/<id:\d+>' => 'delete',
            'PUT media/<id:\d+>' => 'update',
            'POST media' => 'create',
            'GET media' => 'all',
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
     * 新增素材
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/wechat/media",
     *     tags={"Admin - Wechat"},
     *     description="新增素材",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatMediaCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatMedia")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionCreate()
    {
        $model = new WechatMedia();
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                $errors = $model->getFirstErrors();
                throw new Exception($errors, '添加失败 ' . current($errors));
            } else {
                return Format::media($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '关键词已存在');
        }
    }

    /**
     * 修改素材
     * @return array
     * @throws \yii\web\HttpException
     *
     * @SWG\Put(path="/admin/wechat/media/{id}",
     *     tags={"Admin - Wechat"},
     *     description="更新素材",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatMediaCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/AdminWechatMedia")
     *     ),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $this->setAttributes($model);
        try {
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '修改失败');
            } else {
                return Format::media($model);
            }
        } catch (IntegrityException $e) {
            throw new Exception($e->getName(), '修改失败' . $e->getName());
        }
    }

    /**
     * 删除素材
     * @throws \yii\web\HttpException
     *
     * @SWG\Delete(path="/admin/wechat/media/{id}",
     *     tags={"Admin - Wechat"},
     *     description="删除一个素材",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="id", required=true, default="1"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权"),
     *     @SWG\Response(response=404, description="关键词不存在"),
     * )
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model) {
            $model->addFlag(WechatMedia::FLAG_DELETE, true);
        } else {
            throw new Exception($model->getFirstErrors(), '删除失败');
        }
    }

    /**
     * 获取素材列表
     * @return array
     *
     * @SWG\Get(path="/admin/wechat/media",
     *     tags={"Admin - Wechat"},
     *     description="返回素材列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Parameter(in="query", name="type", type="string", description="根据类别查询", default="1"),
     *     @SWG\Parameter(in="query", name="q", type="string", description="根据摘要查询查询"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatMedia")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionAll()
    {
        $query = WechatMedia::find()->where(['not', ['&', 'flag', WechatMedia::FLAG_DELETE]]);
        $q = Yii::$app->request->get('q');
        if (is_numeric($q)) {
            $query->andWhere(['id' => $q]);
        } elseif (!empty($q)) {
            $query->andWhere(['like', 'data', $q]);
        }
        $type = Yii::$app->request->get('type');
        if (is_numeric($type)) {
            $query->andWhere(['type' => $type]);
        }
        $all = $query->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = Format::media($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }


    /**
     * 表单载入模型
     * @param WechatMedia $model
     * @SWG\Definition(
     *     definition="AdminWechatMediaCreate",
     *     type="object",
     *     @SWG\Property(property="type", type="integer", description="0=文本, 1=图文, 2=图片, 3=语音, 4=音乐, 5=视频, 6=小程序", example=0),
     *     @SWG\Property(property="data", type="object", description="对象或者字符串不做任何修改", example="差评君"),
     *     @SWG\Property(property="summary", type="string", description="摘要 搜索用 可以不写", example="123456"),
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
     * @return WechatMedia
     */
    private function findModel($id)
    {
        $model = WechatMedia::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('素材不存在');
        } else {
            return $model;
        }
    }
}
