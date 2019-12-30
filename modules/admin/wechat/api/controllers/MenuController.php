<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\admin\wechat\api\controllers;

use app\models\wechat\User;
use app\models\Admin;
use app\models\Alarm;
use app\modules\api\MetaResponse;
use app\modules\api\Exception;
use Yii;
use yii\filters\AccessControl;
use yii\web\BadRequestHttpException;

/**
 * 管理后台 微信菜单
 *
 * @author William Chan <root@williamchan.me>
 *
 */
class MenuController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'PUT menu/<scenario:[\w-]+>' => 'update',
            'GET menu/<scenario:[\w-]+>' => 'index',
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
     * 获取微信菜单
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141014
     * @param string $scenario
     * @return array
     *
     * @SWG\Get(path="/admin/wechat/menu/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="返回公众号的菜单列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatMenu")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionIndex($scenario)
    {
        $wechat = Yii::$app->wechatApp->setScenario($scenario);
        try {
            $data = $wechat->api('menu/get');
            return Format::menu($data);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 修改微信菜单
     * @see https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421141013
     * @param string $scenario
     * @return array
     *
     * @SWG\Put(path="/admin/wechat/menu/{scenario}",
     *     tags={"Admin - Wechat"},
     *     description="设置公众号的菜单列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(ref="#/parameters/wechatScenario"),
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/AdminWechatMenu")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatMenu")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionUpdate($scenario)
    {
        $wechat = Yii::$app->wechatApp->setScenario($scenario);
        try {
            $params = Yii::$app->request->bodyParams;
            if (count($params) > 0) {
                $data = $this->setAttributes($params);
                $wechat->api('menu/create', $data, 'POST_JSON');
            } else {
                $wechat->api('menu/delete');
            }
            $this->onAdminNotify();
            return $this->actionIndex($scenario);
        } catch (\Exception $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }


    /**
     * 表单载入模型
     *
     * @SWG\Definition(
     *     definition="AdminWechatMenuOne",
     *     type="object",
     *     @SWG\Property(property="name", type="string", description="菜单名", example="点我"),
     *     @SWG\Property(property="type", type="string", description="菜单类型", example="click"),
     *     @SWG\Property(property="key", type="string", description="回复key", example="招商"),
     * )
     */
    private function setAttributes($params)
    {
        $data = [
            'button' => $params,
        ];
        return $data;
    }

    /**
     * 通知管理员，有人修改了菜单
     * @return void
     */
    private function onAdminNotify()
    {
        $user = Yii::$app->user;
        $wechat = Yii::$app->wechatApp;
        $scenario = $wechat->scenario;
        $ids = [];
        if ($scenario === 'chaping') {
            $ids = Alarm::getAllIds(Alarm::FLAG_WECHAT_STAT_CHAPING);
        } elseif ($scenario === 'zhongce') {
            $ids = Admin::find()->where(['&', 'flag', Admin::FLAG_SUPER | Admin::FLAG_TESTING])->select('id')->asArray()->all();
        } else {
            $ids = Alarm::getAllIds(Alarm::FLAG_ALL);
        }
        if (!empty($ids)) {
            $wechat->setScenario('anwang');
            $all = User::findAll($ids);
            foreach ($all as $scenarioUser) {
                $user->sendTemplate(
                    $scenarioUser,
                    'anwang',
                    '',
                    'kM0AkJVt1Josj2hR9IDR5s7vyjkiuqPBWAIo9IgvqFQ',
                    [
                        'first' => '尊贵的管理员您好，微信菜单被修改提醒。' . PHP_EOL,
                        'keyword1' => $scenario,
                        'keyword2' => date('Y/m/d H:i:s'),
                        'remark' => PHP_EOL . '修改人：' . $user->identity->name,
                    ]
                );
            }
        }
    }
}
