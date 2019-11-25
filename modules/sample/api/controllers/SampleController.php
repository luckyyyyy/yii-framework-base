<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\sample\api\controllers;

use app\modules\api\controllers\Controller;
use app\modules\api\Exception;
use Yii;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotAcceptableHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * sample
 *
 * @author William Chan <root@williamchan.me>
 */
class SampleController extends Controller
{

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            // 'GET sample' => 'sample',
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
     * sample
     */
    public function actionView()
    {
        return sample;
    }
}
