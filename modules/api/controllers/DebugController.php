<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api\controllers;

use app\models\Alarm;
use app\components\File;
use app\components\Muggle;
use app\components\WechatMp;
use app\components\Html;
use app\models\ShortUrl;
use app\models\Attachment;
use app\models\AppMarket;
use app\models\FunCode;
use app\models\Identity;
use app\models\wechat\WechatMedia;
use app\modules\UrlRule;
use app\modules\api\MetaResponse;
use app\modules\api\Module;
use app\modules\api\Exception;
use app\search\ProductItem;
use Yii;
use yii\db\Expression;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Endroid\QrCode\QrCode;

/**
 * debug
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="0 - DEBUG - 测试", description="Debug 测试接口 正式服都不开放")
 */
class DebugController extends Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET debug/test' => 'test',
            'GET debug/info' => 'info',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function bearerConfig()
    {
        return [
            'optional' => ['test', 'info'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        return Muggle::isDebugEnv();
    }

    /**
     * 测试
     *
     * @SWG\Get(path="/debug/test",
     *     tags={"0 - DEBUG - 测试"},
     *     description="测试",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/ImageInfoFormat")
     *         )
     *     ),
     * )
     */
    public function actionTest()
    {
        $wechat = Yii::$app->wechatApp;
        return $wechat->scenarios;
    }

    /**
     * phpinfo
     * @SWG\Get(path="/debug/info",
     *     tags={"0 - DEBUG - 测试"},
     *     description="phpinfo",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success"),
     * )
     */
    public function actionInfo()
    {
        phpinfo();
    }
}
