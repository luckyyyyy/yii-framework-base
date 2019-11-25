<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\controllers;

use app\models\ShortUrl;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * 站点
 *
 * @author William Chan <root@williamchan.me>
 */
class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
    public $layout = 'site';

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => 'yii\web\ErrorAction',
            'page' => 'yii\web\ViewAction',
        ];
    }

    /**
     * 首页
     * @return void
     */
    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * 维护提示
     * @throws \yii\web\HttpException
     */
    public function actionOffline()
    {
        $pathInfo = Yii::$app->request->getPathInfo();
        $message = '很抱歉，我们正在进行系统升级维护，大约需要几分钟时间。';
        if (substr($pathInfo, 0, 4) === 'api/') {
            Yii::$app->response->format = 'json';
            return ['errcode' => 503, 'errmsg' => $message];
        } else {
            return $this->render('error', ['name' => '维护中', 'message' => $message]);
        }
    }

    /**
     * 用户登录
     * @throws NotSupportedException
     */
    public function actionLogin()
    {
        throw new \yii\base\NotSupportedException();
    }

    /**
     * 短网址转换
     * @param string $id
     * @return \yii\web\Response
     * @throws \yii\web\HttpException
     */
    public function actionShortUrl($id)
    {
        $model = ShortUrl::findOne(['id' => $id]);
        if ($model === null) {
            throw new NotFoundHttpException('不存在');
        }
        $model->updateCounters(['count' => 1]);
        return $this->redirect($model->realUrl);
    }
}
