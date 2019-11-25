<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\tools\api\controllers;

use app\components\Image;
use app\modules\api\Exception;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * 图片相关
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Tools", description="工具")
 */
class ImageController extends \app\modules\api\controllers\Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'Get create-image' => 'create-image',
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        if (isset($behaviors['bearer'])) {
            $behaviors['bearer']['only'] = ['view'];
        }
        return $behaviors;
    }

    /**
     * 创建图片测试
     * @SWG\Get(path="/tools/create-image",
     *     tags={"Tools"},
     *     produces={"application/json"},
     *     security={},
     *     @SWG\Response(response=404, description="产品不存在"),
     * )
     */
    public function actionCreateImage()
    {
        Yii::$app->wechatApp->scenario = 'B';
        $raw = Yii::$app->wechatApp->fetchWxaCode('pages/community/post/detail', 'test', [
            'auto_color' => false,
            'line_color' => ['r' => 0xff, 'g' => 0, 'b' => 0],
            // 'is_hyaline' => true, // 透明不太好扫 不知道为啥
        ]);
        $im = new Image;
        $im->bgFile = '@app/static/test/test_bg.png';
        $im->font = 'simfang.ttf';
        $im->size = 10;
        $im->color = '#fff';
        $im->addText('你好 世界', 56, 90);
        $im->addImage($raw, 100, 100, 500, 500);
        $images = $im->toPng();
        return Yii::$app->response->sendContentAsFile($images, 'test.png', ['inline' => true, 'mimeType' => 'image/png']);
    }
}
