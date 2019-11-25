<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\base\Exception;

/**
 * 微信数据 爬虫
 * @author William Chan <root@williamchan.me>
 */
class WechatController extends Controller
{
    /**
     * @inheritdoc
     */
    public $layout = 'wechat';

    private $_mp;

    // /**
    //  * @inheritdoc
    //  */
    // public function behaviors()
    // {
    //     return [
    //         'access' => [
    //             'class' => AccessControl::class,
    //             'except' => ['index'],
    //             'rules' => [[
    //                 // 'allow' => true,
    //                 'roles' => ['WECHAT'],
    //             ]],
    //         ],
    //     ];
    // }

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
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        list($route, $params) = Yii::$app->request->resolve();
        if (isset($params['scenario'])) {
            $scenario = $params['scenario'];
            $this->_mp = Yii::$app->wechatMp;
            $this->_mp->setScenario($scenario);
        }
        return true;
    }

    /**
     * 首页
     * @return void
     */
    public function actionIndex($scenario)
    {
        return $this->render('index', [
            'scenario' => $this->_mp->scenario,
            'step' => $this->getStep(),
        ]);
    }

    /**
     * 输出二维码
     * @return yii\web\Response
     */
    public function actionImage($scenario)
    {
        if ($this->getStep() === 1) {
            return Yii::$app->response->sendContentAsFile($this->_mp->getVerifyImage(), 'qrcode.png', ['inline' => true, 'mimeType' => 'image/png']);
        } else {
            throw new \yii\web\BadRequestHttpException();
        }
    }

    /**
     * 检查登录状态
     * @return array
     */
    public function actionCheck($scenario)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $step = $this->getStep();
        if ($step === 1) {
            $ret = $this->_mp->checkLogin();
            if ($ret === true) {
                $this->setStep(2);
                return ['result' => true];
            }
            return ['result' => $ret];
        } elseif ($step === 2) {
            return ['result' => true];
        }
    }

    /**
     * 获取公众号图文消息统计数据
     * @return array
     */
    public function actionAppmsg($scenario)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $step = $this->getStep();
        if ($step === 2) {
            try {
                $data = $this->_mp->getAppmsg();
                if (!isset($data['sent_list'][0]['appmsg_info'])) {
                    $this->setStep(1);
                    throw new Exception();
                }
                return $data;
            } catch (\Exception $e) {
                $this->setStep(1);
                throw $e;
            }
        }
    }

    /**
     * 设置 step
     */
    private function setStep($step)
    {
        $key = 'WECHAT_MP_STEP_' . $this->_mp->getScenario();
        Yii::$app->cache2->set($key, $step);
    }

    /**
     * 获取 step
     * @return int
     */
    private function getStep()
    {
        $key = 'WECHAT_MP_STEP_' . $this->_mp->getScenario();
        $step = Yii::$app->cache2->get($key);
        return $step !== false ? $step : 1;
    }
}
