<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\controllers;

use app\models\Identity;
use app\models\PointLog;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * 兑吧积分商城组件
 * @see https://www.duiba.com.cn/
 *
 * @author William Chan <root@williamchan.me>
 */
class DuibaController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'login';

    /**
     * @var string Base API URL
     */
    public $baseUrl = 'https://www.duiba.com.cn';

    /**
     * @var string AppKey
     */
    public $appKey = '';

    /**
     * @var string AppSecret
     */
    public $appSecret = '';

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if ($action->id !== 'login' && !$this->checkSign()) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 'fail', 'errorMessage' => '签名校验失败', 'credits' => 0];
            return false;
        }
        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);
        if (is_array($result)) {
            Yii::$app->response->format = Response::FORMAT_JSON;
        }
        return $result;
    }

    /**
     * 免登录接口
     * @param string $dbredirect 重定向至兑吧地址
     * @return \yii\web\Response
     */
    public function actionLogin($dbredirect = null)
    {
        $params = [];
        $user = Yii::$app->user;
        if ($user->isGuest) {
            $params['uid'] = 'not_login';
            $params['credits'] = '0';
            return $user->loginRequired();
        } else {
            $params['uid'] = $user->id;
            $params['credits'] = $user->identity->point;
        }
        $params['appKey'] = $this->appKey;
        $params['timestamp'] = time() . '000';
        if ($dbredirect !== null) {
            $params['redirect'] = $dbredirect;
        }
        $params['sign'] = $this->genSign($params);

        $url = $this->baseUrl . '/autoLogin/autologin?' . http_build_query($params);
        return $this->redirect($url);
    }

    /**
     * 积分消费
     * @return array
     */
    public function actionConsume()
    {
        $params = Yii::$app->request->getQueryParams();
        $identity = Identity::findOne(['id' => $params['uid']]);
        if ($identity === null) {
            return ['status' => 'fail', 'errorMessage' => '无效的 uid', 'credits' => 0];
        } elseif ($identity->point < $params['credits']) {
            return ['status' => 'fail', 'errorMessage' => '积分余额不足', 'credits' => $identity->point];
        }
        $model = new PointLog([
            'user_id' => $identity->id,
            'num' => 0 - intval($params['credits']),
            'order_num' => $params['orderNum'],
            'extra' => $params,
            'remark' => '【兑吧】' . (empty($params['description']) ? $params['orderNum'] : $params['description']),
        ]);
        try {
            if (!$model->save()) {
                return ['status' => 'fail', 'errorMessage' => 'Save error', 'credits' => $identity->point];
            }
            return ['status' => 'ok', 'bizId' => 'duiba-' . $model->id, 'credits' => $identity->point + $model->num];
        } catch (\Exception $e) {
            return ['status' => 'fail', 'errorMessage' => 'Exception', 'credits' => $identity->point];
        }
    }

    /**
     * 通知结果
     * @return array
     */
    public function actionNotify()
    {
        // 只需处理 success=false 的情况
        $params = Yii::$app->request->getQueryParams();
        if (isset($params['success']) && $params['success'] === 'false') {
            $model = PointLog::findOne(['order_num' => $params['orderNum']]);
            if ($model !== null) {
                $back = new PointLog([
                    'user_id' => $model->user_id,
                    'num' => 0 - $model->num,
                    'extra' => $params,
                    'remark' => '【兑吧】' . $params['orderNum'],
                ]);
                try {
                    $back->save(false);
                    $model->updateAttributes(['order_num' => null]); // 清空 order_num 避免重复操作
                } catch (\Exception $e) {
                    return 'fail: ' . $e->getMessage();
                }
            }
        }
        return 'ok';
    }

    /**
     * 虚拟商品充值
     * @return array
     * @todo
     */
    public function actionVirtual()
    {
    }

    /**
     * 加积分接口
     * @return array
     */
    public function actionAdd()
    {
        $params = Yii::$app->request->getQueryParams();
        $identity = Identity::findOne(['id' => $params['uid']]);
        if ($identity === null) {
            return ['status' => 'fail', 'errorMessage' => '无效的 uid', 'credits' => 0];
        }
        $model = PointLog::findOne(['order_num' => $params['orderNum']]);
        if ($model === null) {
            $model = new PointLog([
                'user_id' => $identity->id,
                'num' => intval($params['credits']),
                'order_num' => $params['orderNum'],
                'extra' => $params,
                'remark' => '【兑吧】' . (empty($params['description']) ? $params['orderNum'] : $params['description']),
            ]);
            try {
                if (!$model->save()) {
                    return ['status' => 'fail', 'errorMessage' => 'Save error', 'credits' => $identity->point];
                }
            } catch (\Exception $e) {
                return ['status' => 'fail', 'errorMessage' => 'Exception', 'credits' => $identity->point];
            }
        }
        return ['status' => 'ok', 'bizId' => 'duiba-' . $model->id, 'credits' => $identity->point + $model->num];
    }

    /**
     * 检查签名
     * @return bool
     */
    private function checkSign()
    {
        $req = Yii::$app->request;
        $params = $req->isGet ? $req->getQueryParams() : $req->getBodyParams();
        if (!isset($params['sign']) || strlen($params['sign']) !== 32) {
            return false;
        }
        $sign = $params['sign'];
        unset($params['sign']);
        return $sign === $this->genSign($params);
    }

    /**
     * Generate MD5 sign
     * @param array $params
     * @return string
     */
    private function genSign($params)
    {
        if (isset($params['appKey'])) {
            $params['appKey'] = $this->appKey;
        }
        $params['appSecret'] = $this->appSecret;
        ksort($params);
        return md5(implode('', array_values($params)));
    }
}
