<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\controllers;

use Endroid\QrCode\QrCode;
use app\models\activity\AlipayAntCreditPay;
use app\models\Identity;
use app\models\wechat\User;
use app\models\wechat\WechatKeyword;
use app\models\wechat\WechatMedia;
use app\models\wechat\WechatQrcode;
use app\components\Html;
use Yii;
use yii\web\Controller;

/**
 * 公众号机器人
 * @author William Chan <root@williamchan.me>
 */
class RobotController extends Controller
{
    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    private $_wechatApp;
    private $_replier;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $scenario = strtr($action->id, ['-' => '_', '/' => '_']);
        $this->_wechatApp = Yii::$app->wechatApp->setScenario($scenario);
        $this->_replier = $this->_wechatApp->createReplier([
            'debug' => YII_DEBUG,
            'logFile' => Yii::getAlias('@app/runtime/robot_' . $scenario . '.log'),
        ]);
        /* @var $this->_wechatApp \app\components\WechatApp */
        /* @var $this->_replier \app\components\WechatReplier */
        return true;
    }

    /**
     * 公众号A
     */
    public function actionA()
    {
        $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
            ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
            ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
            ->addHandler([$this, 'onReplyByActivity']) // 活动事件
            ->addHandler([$this, 'onReplyByZhongCe']) // 众测回复
            ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
            ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
            ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
            ->run();
    }

    // /**
    //  * 公众号B
    //  */
    // public function actionB()
    // {
    //     $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
    //         ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
    //         ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
    //         ->addHandler([$this, 'onReplyByZhongCe']) // 众测回复
    //         ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
    //         // ->addHandler([$this, 'onReplyByTest'])
    //         ->addHandler([$this, 'onReplyScan']) // 众测带参数二维码扫码
    //         ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
    //         ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
    //         ->run();
    // }

    // /**
    //  * 公众号C
    //  */
    // public function actionC()
    // {
    //     $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
    //         ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
    //         ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
    //         ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
    //         ->addHandler([$this, 'onReplyScan']) // 众测带参数二维码扫码
    //         ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
    //         ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
    //         ->run();
    // }

    // /**
    //  * 公众号D
    //  */
    // public function actionD()
    // {
    //     $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
    //         ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
    //         ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
    //         ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
    //         ->addHandler([$this, 'onReplyScan']) // 众测带参数二维码扫码
    //         ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
    //         // ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
    //         ->run();
    // }

    // /**
    //  * 小程序A
    //  */
    // public function actionAA()
    // {
    //     $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
    //         ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
    //         ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
    //         ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
    //         ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
    //         ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
    //         ->run();
    // }

    // /**
    //  * 小程序B
    //  */
    // public function actionBB()
    // {
    //     $this->_replier->on('event', [$this, 'onEvent']) // 事件处理器
    //         ->addHandler([$this, 'onUpdateUserActive']) // 处理用户活跃时间
    //         ->on('event', [$this, 'onReplyByEvent']) // 对事件进行回复
    //         ->addHandler([$this, 'onReplyByKeyword']) // 对文本进行回复
    //         ->addHandler([$this, 'onReplyByAnyKeyword']) // 对任意文本进行回复
    //         ->addHandler([$this, 'onReplyCustomer']) // 转发到多客服
    //         ->run();
    // }

    /**
     * 对微信事件进行一些处理
     * @param WechatReplier $wx
     * @return bool
     */
    public function onEvent($wx)
    {
        $identity = $this->findIdentity($wx);
        if ($identity) {
            $model = $identity->user;
            /* @var $model \app\models\wechat\User */
            if ($wx->isHello() || $wx->getScanScene() !== false) {
                $model->time_subscribe = time();
            } elseif ($wx->isCancel()) {
                $model->time_cancel = time();
                $model->isFollow = false;
            } elseif ($wx->isClick() || $wx->isEvent('VIEW')) {
                $model->time_active_menu = time();
            }
            if (!$wx->isCancel()) {
                $model->isFollow = true;
            }
        }
        return false;
    }

    /**
     * 对任意操作的用户 进行活跃时间更新 不更新取关
     * @param WechatReplier $wx
     * @return bool
     */
    public function onUpdateUserActive($wx)
    {
        $identity = $this->findIdentity($wx);
        if ($identity) {
            $model = $identity->user;
            /* @var $model \app\models\wechat\User */
            if (!$wx->isCancel() && !$wx->isEvent('TEMPLATESENDJOBFINISH')) {
                $model->time_active = time();
            }
            $model->save(false);
        }
        return false;
    }

    /**
     * 对事件进行回复
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByEvent($wx)
    {
        if ($wx->isHello() || $wx->isEnterSession()) {
            // @fixme 新关注 不是扫码进来 回复他关注信息
            return $this->findByKeyword($wx, WechatKeyword::WECHAT_HELLO_KEYWORD);
        } elseif ($wx->isClick()) {
            $key = $wx->getClickKey();
            $media = WechatMedia::findOne($key);
            // 菜单点击数量去微信后台看吧
            if ($media) {
                $this->formatMessage($wx, $media);
                return true;
            }
        }
        return false;
    }

    /**
     * 活动事件
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByActivity($wx)
    {
        $identity = $this->findIdentity($wx);
        if ($identity) {
            $text = $wx->getText();
            if (preg_match('/支付宝自己帮我清空花呗账单\+[1][3-9]\d{9}$/', $text) === 1) {
                if (time() < strtotime('2018-11-12 10:00:00')) {
                    if (($model = AlipayAntCreditPay::findOne(['identity_id' => $identity->id])) !== null) {
                        $wx->asText('您的抽奖兑换码是「' . $model->id . '」，我们将于11月12日公布开奖结果。');
                    } else {
                        try {
                            $model = new AlipayAntCreditPay([
                                'identity_id' => $identity->id,
                                'message' => $text,
                            ]);
                            $model->save();
                            $wx->asText('您的抽奖兑换码是「' . $model->id . '」，我们将于11月12日公布开奖结果。');
                        } catch (\Exception $e) {
                            $wx->asText('抱歉，参与活动的同学太热情，请再试一次。');
                        }
                    }
                } else {
                    $wx->asText('该活动已结束，请持续关注XX，稍后将有更多福利哦。');
                }
                return true;
            }
        }
        return false;
    }


    /**
     * 图片回复 写死的 适用于菜单和文本
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByZhongCe($wx)
    {
        // $keyword = '';
        // if ($wx->isClick()) {
        //     $keyword = $wx->getClickKey();
        // } else {
        //     $keyword = $wx->getText();
        // }
        // // 众测回复图片用 临时
        // if ($keyword && strpos($keyword, '众测') !== false) {
        //     $file = Yii::getAlias('@app/static/media/images/follow_zhongce.jpg');
        //     $wx->asImage($file);
        //     return true;
        // }
        return false;
    }

    /**
     * 从数据库获取回复关键词
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByKeyword($wx)
    {
        if (!$wx->isEvent()) {
            $text = $wx->getText();
            return $this->findByKeyword($wx, $text);
        }
        return false;
    }

    /**
     * 对任意文本进行回复
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByAnyKeyword($wx)
    {
        if (!$wx->isEvent()) {
            $openid = (string) $wx->in->FromUserName;
            $key = 'WECHAT_ANY_KEYWORD_REPLY_' . $this->_wechatApp->scenario . '_' . $openid;
            $cache = Yii::$app->cache2;
            if (!$cache->exists($key)) {
                $cache->set($key, true, 86400);
                return $this->findByKeyword($wx, WechatKeyword::WECHAT_ANY_KEYWORD);
            }
        }
        return false;
    }


    /**
     * 扫码关注相关
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyScan($wx)
    {
        if (($scene = $wx->getScanScene()) === false) {
            return false;
        }
        return $this->replyScanScene($wx, $scene);
    }

    /**
     * 转发到多客服
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyCustomer($wx)
    {
        $wx->asCustomer();
        return true;
    }

    /**
     * 测试
     * @param WechatReplier $wx
     * @return bool
     */
    public function onReplyByTest($wx)
    {
        if (!$wx->isEvent()) {
            $text = $wx->getText();
            // $identity = $this->findIdentity($wx);
            $wx->asVoice('voice_' . $wx->in->FromUserName. '.mp3', Yii::$app->baiduAi->text2Audio($text));
            // $qrCode = new QrCode($identity->name . '你是个猪');
            // $wx->asImage('qrcode' . $wx->in->FromUserName . '.png', $qrCode->writeString());
            return true;
        }
        return false;
    }

    /**
     * 从二维码场景回复用户内容
     * @param WechatReplier $wx
     * @param string|int $scene
     * @return bool
     */
    private function replyScanScene($wx, $scene)
    {
        if (substr($scene, 0, strlen('WECHAT_MEDIA_')) === 'WECHAT_MEDIA_') {
            $qrcode = WechatQrcode::findOne(['scene' => $scene]);
            if ($qrcode) {
                $qrcode->updateCounters(['count' => 1]);
                $this->formatMessage($wx, $qrcode->media);
                return true;
            }
        } elseif (substr($scene, 0, strlen('WECHAT_KEYWORD_')) === 'WECHAT_KEYWORD_') {
            // @fixme 只是兼容用 即将删除
            $keyword = preg_split('/WECHAT_KEYWORD_+/i', $scene, -1, PREG_SPLIT_NO_EMPTY);
            if (isset($keyword[0]) && $keyword[0] !== '') {
                return $this->findByKeyword($wx, $keyword[0]);
            }
        }
        //
        return false;
    }

    /**
     * 从数据库里获取数据 对关键词进行回复
     * @param WechatReplier $wx
     * @param string $keyword
     * @return bool
     */
    private function findByKeyword($wx, $keyword)
    {
        if ($keyword) {
            $model = WechatKeyword::findOne(['keyword' => $keyword]);
            if (!$model) {
                $data = $this->getMatchKeyword();
                foreach ($data as $item) {
                    if (strpos($keyword, $item['keyword']) !== false) {
                        $model = WechatKeyword::findOne($item['id']);
                        // TODO 没有匹配的情况
                        // 这除非是缓存和数据库对不上 这种情况理应不存在
                    }
                }
            }
            if ($model) {
                $model->updateCounters(['count' => 1]);
                $this->formatMessage($wx, $model->media);
                return true;
            }
        }
        return false;
    }

    /**
     * 格式化微信信息
     * @param WechatReplier $wx
     * @param WechatMedia $model
     */
    private function formatMessage($wx, $model)
    {
        if ($model) {
            $wechat = $this->_wechatApp;
            $isWxapp = $wechat->isWxapp;
            $user = Yii::$app->user;
            if ($isWxapp) {
                $openid = (string) $wx->in->FromUserName;
                // 小程序上用户是拿不到用户信息的
                if (
                    // 小程序只允许发 文本 图片 APP 图文
                    $model->type === WechatMedia::TYPE_NEWS ||
                    $model->type === WechatMedia::TYPE_TEXT ||
                    $model->type === WechatMedia::TYPE_IMAGE ||
                    $model->type === WechatMedia::TYPE_APP ||
                    $model->type === WechatMedia::TYPE_LINK
                ) {
                    // 兼容公众号图文
                    $user->sendCustomer(
                        'OPENID:' . $openid,
                        $wechat->scenario,
                        $model->wechatCustomerFormat($model->type === WechatMedia::TYPE_NEWS ? WechatMedia::TYPE_LINK : null),
                        $model->wechatCustomerType($model->type === WechatMedia::TYPE_NEWS ? WechatMedia::TYPE_LINK : null)
                    );
                } else {
                    $wx->asCustomer(); // 转到客服
                }
                /**
                 * 小程序比较特殊
                 * 服务器收到请求必须做出下述回复，这样微信服务器才不会对此作任何处理，并且不会发起重试，否则，将出现严重的错误提示。详见下面说明：
                 * 1、直接回复success（推荐方式）
                 * @see https://developers.weixin.qq.com/miniprogram/dev/api/custommsg/receive.html#%E6%8E%A5%E6%94%B6%E6%B6%88%E6%81%AF%E5%92%8C%E4%BA%8B%E4%BB%B6
                 */
                $wx->asEmpty();
            } else {
                $identity = $this->findIdentity($wx);
                if ($model->type === WechatMedia::TYPE_NEWS) {
                    foreach ($model->data as $item) {
                        $wx->addNews($this->replaceMessage($item['msg'], $identity), $item['url'] ?? '', $item['pic'] ?? '', $item['desc'] ?? '');
                    }
                } elseif ($model->type === WechatMedia::TYPE_TEXT) {
                    $wx->asText($this->replaceMessage($model->data, $identity));
                } elseif ($model->type === WechatMedia::TYPE_IMAGE) {
                    $wx->asImage($model->data);
                } elseif ($model->type === WechatMedia::TYPE_VOICE) {
                    $wx->asVoice($model->data);
                } elseif ($model->type === WechatMedia::TYPE_MUSIC) {
                    $wx->asMusic($model->data);
                } elseif ($model->type === WechatMedia::TYPE_VIDEO) {
                    $wx->asVideo($model->data);
                } elseif ($model->type === WechatMedia::TYPE_APP) {
                    // 小程序不支持被动回复 所以主动回复
                    $user->sendCustomer(
                        $identity,
                        $wechat->scenario,
                        $model->wechatCustomerFormat,
                        $model->wechatCustomerType
                    );
                    $wx->asEmpty();
                }
            }
        } else {
            $wx->asEmpty();
        }
    }

    /**
     * 替换一些特殊的字符
     * @param string $message
     * @param Identity $identity
     * @return string
     */
    private function replaceMessage($message = '', $identity)
    {
        if ($identity) {
            if (strpos($message, '$name') !== false) {
                $message = str_replace('$name', $identity->name, $message);
            }
            if (strpos($message, '$point') !== false) {
                $message = str_replace('$point', $identity->point, $message);
            }
        }
        return $message;
    }

    /**
     * 根据openid 查询对应的用户 没有通行证 帮创建一个（小程序除外，拿不到unionid）
     * @param WechatReplier $wx
     * @return Identity|null
     */
    private function findIdentity($wx)
    {
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        $isWxapp = $this->_wechatApp->isWxapp;
        if ($isWxapp) {
            return null;
        }
        if ($user->isGuest) {
            return $user->findIdentityByWechatMessage($wx);
        }
        return $user->identity;
    }

    /**
     * 获取所有需要模糊匹配的关键词 存入redis
     * @return array
     */
    private function getMatchKeyword()
    {
        $data = WechatKeyword::cache();
        if (!$data) {
            $data = WechatKeyword::find()
                ->select(['id', 'keyword'])
                ->where(['is_match' => 'Y'])
                ->orderBy(['id' => SORT_DESC])
                ->asArray()
                ->all();
            WechatKeyword::cache($data);
        }
        return $data;
    }
}
