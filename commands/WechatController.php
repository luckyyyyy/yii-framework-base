<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use app\models\Admin;
use app\models\wechat\WechatMp;
use app\models\wechat\User;
use app\models\Cron;
use app\models\wechat\WechatMedia;
use app\models\Alarm;
use app\models\Identity;
use Yii;
use yii\helpers\Json;
use yii\helpers\Console;

/**
 * 微信工具
 *
 * @author hightman <hightman@cloud-sun.com>
 * @author William Chan <root@williamchan.me>
 */
class WechatController extends Controller
{

    // 微信公众号用户渠道
    const WECHAT_USER_CHANNEL = [
        0 => '其他',
        1 => '搜索',
        17 => '名片分享',
        30 => '二维码',
        43 => '文章右上角菜单',
        51 => '支付后关注',
        57 => '文章内公众号名称',
        75 => '公众号文章广告',
        78 => '朋友圈广告',
    ];

    /**
     * 打印 tokens
     * @param string $scenario 微信别名
     */
    public function actionToken($scenario = 'A')
    {
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($scenario);
        /* @var $wechat \app\components\WechatApp */
        echo '=== ', $wechat->appId, ' ===', PHP_EOL;
        echo '=== Access Token (', strlen($wechat->appToken), ') ===', PHP_EOL;
        echo $wechat->appToken, PHP_EOL;
        echo '=== Js ticket (', strlen($wechat->jsTicket), ') ===', PHP_EOL;
        echo $wechat->jsTicket, PHP_EOL;
    }


    /**
     * 获取用户最新信息
     * @param string $scenario 微信别名
     */
    public function actionUserInfo($scenario = 'A', $id)
    {
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($scenario);

        $identity = Identity::findOne($id);
        if ($identity && $identity->user) {
            /* @var $wechat \app\components\WechatApp */
            echo '=== OpenID (', $identity->user->openid, ') ===', PHP_EOL;
            var_dump($wechat->fetchUserInfo($identity->user->openid));
        }
    }


    /**
     * 上传永久素材
     * @param string $scenario
     * @return void
     */
    public function actionUploadMaterial($scenario = 'A', $url)
    {
        $content = file_get_contents($url);
        $wehcat = Yii::$app->get('wechatApp')->setScenario($scenario);
        $res = $wehcat->api('material/add_material?type=image', [
            '@media' => ['name' => basename($url), 'content' => $content],
        ], 'POST');
        var_export($res);
    }

    /**
     * 发送统计数据
     * @param string $scenario 公众号别名
     */
    public function actionStat($scenario = 'A', $day = '-1 day')
    {
        $key = 'WECHAT_EVERY_DAY_STAT_' . $scenario . '_' . $day;
        $cache = Yii::$app->cache2;
        if ($cache->exists($key)) {
            return;
        }
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($scenario);
        $yesterDay = strtotime($day);
        $date = date('Y-m-d', $yesterDay);
        $stat = $wechat->datacubeApi('getusersummary', [
            'begin_date' => $date,
            'end_date' => $date,
        ]);
        // var_dump($stat);die;
        echo '==== stat ', $scenario,  ' stat begin ====', PHP_EOL, PHP_EOL;
        // 查询今日活跃人数
        $active = (int) User::find()->where(['>=', 'time_active', $yesterDay])->count();
        // 查询今日点击菜单人数
        $activeMenu = (int) User::find()->where(['>=', 'time_active_menu', $yesterDay])->count();
        $subscribe = 0;
        $cancel = 0;
        $str = <<<EOF
公众号统计数据
关注人数：%d
取关人数：%d
活跃人数（菜单和对话）：%d
点击菜单人数：%d
净增关注：%d

关注人数来源渠道如下

EOF;

        foreach ($stat['list'] as $data) {
            $subscribe += $data['new_user'];
            $cancel += $data['cancel_user'];
            $str .= (self::WECHAT_USER_CHANNEL[$data['user_source']] ?? 'CHANNEL' . $data['user_source']) . '：' . $data['new_user'] . "\n";
        }
        // 不知道他返回的是字符串还是数字
        if ($subscribe == 0 && $cancel == 0) {
            echo '==== stat skip, no data ====', PHP_EOL;
            return;
        }
        $text = sprintf(
            $str,
            $subscribe,
            $cancel,
            $active,
            $activeMenu,
            $subscribe - $cancel
        );
        echo $text, PHP_EOL;
        $data = [
            'first' => ['value' => '', 'color' => '#173177'],
            'keyword1' => ['value' => $scenario, 'color' => '#173177'],
            'keyword2' => ['value' => $date, 'color' => '#173177'],
            'keyword3' => ['value' => $text, 'color' => '#173177'],
            'remark' => ['value' => PHP_EOL . '此消息由系统自动生成报告发送给管理员', 'color' => '#888888'],
        ];
        // just
        $template = 'I2D2RqOCw5m33y6rbpr1epdE1czUoiTkAlub2wKhaXc';
        $wechat->setScenario('anwang');
        $ids = [];
        if ($scenario === 'zhongce') {
            $ids = Admin::find()->where(['&', 'flag', Admin::FLAG_SUPER | Admin::FLAG_TESTING])->select('id')->asArray()->all();
        } elseif ($scenario === 'A') {
            $ids = Alarm::getAllIds(Alarm::FLAG_WECHAT_STAT_CHAPING);
        } elseif ($scenario === 'B') {
            $ids = Alarm::getAllIds(Alarm::FLAG_WECHAT_STAT_HEISHI);
        }
        if (!empty($ids)) {
            $users = User::findAll($ids);
            foreach ($users as $user) {
                if ($user->allowSendTemplate) {
                    $wechat->sendTemplate($user->openid, '', $template, $data);
                }
            }
        }
        // 一天只能统计一次
        $cache->set($key, true, 86400 - (time() - strtotime('today')));
        echo '==== stat end ====', PHP_EOL;
    }

    /**
     * 批量发送客服消息
     * @param int $id
     */
    public function actionSendCustomer($id)
    {
        $cron = Cron::findOne($id);
        if (!$cron || !isset($cron->extra['scenario']) || !(isset($cron->extra['media_id']) || isset($cron->extra['message']))) {
            $this->stdout('[SEND_CUSTOMER] Cron not found or Incorrect parameter' . PHP_EOL, Console::FG_RED);
            return 2;
        }
        $params = $cron->extra;
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($params['scenario']);
        $all = User::find()
            ->andWhere(['is_follow' => 'Y'])
            ->andWhere(['>', 'time_active', time() - 86400 * 2])
            ->createCommand()
            ->queryAll();
        if (count($all) === 0) {
            $this->stdout('[SEND_CUSTOMER] Send failed, No matching user' . PHP_EOL, Console::FG_RED);
            return 0;
        }
        if (!empty($params['media_id'])) {
            $media = WechatMedia::findOne($params['media_id']);
            if ($media) {
                $this->batchCustomer($all, $media->wechatCustomerFormat, $media->wechatCustomerType);
            } else {
                $this->stdout('[SEND_CUSTOMER] Media not found.' . PHP_EOL, Console::FG_RED);
                return 2;
            }
        } elseif (!empty($params['message'])) {
            $this->batchCustomer($all, $params['message'], $media->wechatCustomerFormat, 'text');
        }
    }

    /**
     * 批量发送模板消息
     * @param int $id
     */
    public function actionSendTemplate($id)
    {
        $cron = Cron::findOne($id);
        if (!$cron || !isset($cron->extra['scenario']) || !isset($cron->extra['template'])) {
            $this->stdout('[SEND_TEMPLATE] Cron not found or Incorrect parameter' . PHP_EOL, Console::FG_RED);
            return 2;
        }
        $params = $cron->extra;
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($params['scenario']);
        $all = User::find()
            ->andWhere(['is_follow' => 'Y'])
            ->createCommand()
            ->queryAll();
        if (count($all) === 0) {
            $this->stdout('[SEND_TEMPLATE] Send failed, No matching user' . PHP_EOL, Console::FG_RED);
            return 0;
        }
        $data = [];
        foreach ($params['data'] as $dat) {
            $data[$dat['key']] = [
                'value' => $dat['value'],
                'color' => $dat['color'],
            ];
        }
        $this->batchTemplate($all, $params['template'], $data, $params['url']);
    }

    /**
     * 获取文章阅读量
     * @param $scenario
     * @return int
     */
    public function actionMp($scenario)
    {
        $wechatMp = Yii::$app->get('wechatMp');
        $wechatMp->setScenario($scenario);
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario($scenario);
        try {
            $data = $wechatMp->getAppmsg(0, 1);
            $model = new WechatMp();

            $message = current($data['sent_list']);
            $list = $message['appmsg_info'];
            $data = [];

            foreach ($list as $item) {
                $data[] = [
                    'read_num' => $item['read_num'],
                    'appmsgid' => $item['appmsgid'],
                    'title' => $item['title'],
                ];
            }
            $model->msgid = $message['msgid'];
            $model->data = Json::encode($data);
            return $model->save(false);
        } catch (\Exception $e) {
            $cache = Yii::$app->cache;
            $key = 'WECHAT_MP_NEED_SCAN_QRCODE_' . $scenario;
            if (!$cache->get($key)) {
                $this->sendNeedScanCodeMsg($scenario);
                $cache->set($key, true, 60 * 60 * 12); // 12h
            }
        }
    }


    /**
     * 发送扫码授权请求
     * @param $scenario
     */
    private function sendNeedScanCodeMsg($scenario)
    {
        $template = 'MxviMYWrvOTB8WnphUI9KZbL08mlOZFYfJIDc6WTWBs';
        $wechat = Yii::$app->get('wechatApp');
        $wechat->setScenario('anwang');
        $date = date('Y-m-d H:i', time());
        $data = [
            'first' => ['value' => '授权过期，需要扫码啦' . PHP_EOL, 'color' => '#FF0000'],
            'keyword1' => ['value' => $scenario . ' 公众号后台授权过期', 'color' => '#173177'],
            'keyword2' => ['value' => $date, 'color' => '#173177'],
            'keyword3' => ['value' => '点击此消息扫码授权', 'color' => '#173177'],
            'remark' => ['value' => PHP_EOL . '此消息由系统自动生成报告发送给管理员', 'color' => '#888888'],
        ];
        $ids = [];
        if ($scenario === 'A') {
            $ids = Alarm::getAllIds(Alarm::FLAG_WECHAT_ALARM_CHAPING);
        }
        if (!empty($ids)) {
            $users = User::findAll($ids);
            foreach ($users as $user) {
                if ($user->allowSendTemplate) {
                    $wechat->sendTemplate($user->openid, Yii::$app->params['host.api'] . '/wechat/' . $scenario, $template, $data);
                }
            }
        }
    }
}
