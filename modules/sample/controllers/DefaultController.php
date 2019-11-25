<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\sample\controllers;

use app\components\Html;
use app\controllers\MultiAgent;
use app\modules\UrlCustomizable;
use Yii;

/**
 * 默认控制器
 *
 * @author William Chan <root@williamchan.me>
 */
class DefaultController extends MultiAgent implements UrlCustomizable
{
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            '' => 'test',
        ];
    }

    /**
     * 首页
     * @return \yii\web\Response
     */
    public function actionTest()
    {
        return $this->render('index');
    }

    /**
     * @inheritdoc
     */
    public function mActions()
    {
        return [];
    }
}
