<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api\controllers;

use app\modules\UrlCustomizable;
use Yii;
use yii\filters\auth\HttpBearerAuth;

/**
 * API 基础类
 * Bearer/Cookie 认证
 *
 * @author William Chan <root@williamchan.me>
 */
abstract class Controller extends \yii\web\Controller implements UrlCustomizable
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * 定义 URL 规则集
     * 例如：['GET posts/<id:\d+>' => 'view']
     * @return array
     */
    public static function urlRules()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // bearer
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        if ($user->isGuest) {
            // try bearer
            $config = $this->bearerConfig();
            if ($config !== false) {
                $user->enableSession = false;
                $behaviors['bearer'] = array_merge([
                    'class' => HttpBearerAuth::class,
                    'realm' => 'MUGGLE API',
                ], $config);
            }
        }
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $user = Yii::$app->user;
        $user->registerUrl = null;
        if (!Yii::$app->request->isGet) {
            Yii::$app->db->enableSlaves = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * Bearer 认证配置，返回 false 禁用
     * @return array|bool
     */
    protected function bearerConfig()
    {
        return [];
    }
}
