<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use app\models\wechat\WechatMp;
use app\models\wechat\User;
use app\models\wechat\WechatMedia;
use app\models\Alarm;
use app\models\Identity;
use app\components\LogTrait;
use Yii;
use yii\helpers\Json;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * 微信公众号工具
 *
 * @author William Chan <root@williamchan.me>
 */
class WechatMpController extends Controller
{

    use LogTrait;

    public $scenario = 'A';
    public $wechatMp;

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $this->wechatMp = Yii::$app->wechatMp->setScenario($this->scenario);
        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'scenario';
        return $options;
    }

    /**
     * 分析文章评论内容
     * @param int $begin 文章发布时间 0=当天发布 1=上一次发布 etc...
     * @param int $count
     */
    public function actionAnalysis($begin, $count = 7)
    {
        $qcloud = Yii::$app->qcloud;
        $baseDir = Yii::getAlias('@app/runtime/wechatmp/');
        try {
            $data = $this->wechatMp->getAppmsg($begin, $count);
            foreach ($data['sent_list'] as $day) {
                $time = date('Y-m-d', $day['sent_info']['time']);
                if (!Console::confirm('export and analysis ' . $time . ' record?', true)) {
                    continue;
                }
                echo '---------- analysis: ', $time, ' ----------', PHP_EOL;
                $dir = $baseDir . $time;
                FileHelper::createDirectory($dir);
                foreach ($day['appmsg_info'] as $message) {
                    echo '分析文章评论：' . $message['title'], PHP_EOL;
                    $text = "昵称,内容,是否精选,形容词,名词\n";
                    $comments = $this->fetchAllComment($message['comment_id']);
                    echo 'Analysis All Comment', PHP_EOL;
                    Console::startProgress(0, count($comments));
                    foreach ($comments as $key => $comment) {
                        Console::updateProgress($key + 1, count($comments));
                        try {
                            $res = $qcloud->textLexicalAnalysis($comment['content']);
                        } catch (\Exception $e) {
                            $this->log($e->getMessage());
                            continue;
                        }
                        $types = [];
                        if ($res && (isset($res['tokens']) || isset($res['combtokens']))) {
                            foreach ($res['tokens'] as $value) {
                                if (!isset($types[$value['wtype']])) {
                                    $types[$value['wtype']] = [];
                                }
                                $types[$value['wtype']][] = $value['word'];
                                $types[$value['wtype']] = array_unique($types[$value['wtype']]);
                            }
                            foreach ($res['combtokens'] as $value) {
                                if (!isset($types[$value['cls']])) {
                                    $types[$value['cls']] = [];
                                }
                                $types[$value['cls']][] = $value['word'];
                                $types[$value['cls']] = array_unique($types[$value['cls']]);
                            }
                        }
                        $adjective = '';
                        if (isset($types['形容词'])) {
                            $adjective = implode('|', $types['形容词']);
                        }
                        $noun = '';
                        if (isset($types['名词'])) {
                            $noun = implode('|', $types['名词']);
                        }
                        $txt = str_replace(["\n", "\r"], ["\t", "\t"], "$comment[nick_name],$comment[content],$comment[is_elected],$adjective,$noun");
                        $text .= "$txt\n";
                    }
                    $text .= "\n------ info ------\n";
                    $text .= '文章：' . $message['title'] . "\n";
                    $text .= '地址：' . $message['content_url'] . "\n";
                    $text .= '阅读数：' . $message['read_num'] . "\n";
                    $text .= '评论数：' . $message['comment_num'] . "\n";
                    $text .= '点赞数：' . $message['like_num'] . "\n";
                    file_put_contents($dir . '/' . $message['title'] . '.csv', $text);
                    Console::endProgress('done.' . PHP_EOL);
                }
            }
        } catch (\Exception $e) {
            $this->log($e->getMessage());
            echo 'ERROR: ', $e->getMessage();
        }
    }

    /**
     * 获取所有评论
     * @param string $id
     * @return void
     */
    private function fetchAllComment($id)
    {
        $begin = 0;
        $result = [];
        $count = 50;
        echo 'Fetch All Comment ' . $id, PHP_EOL;
        while (true) {
            echo '.';
            $data = $this->wechatMp->getAppmsgComment($id, $begin, $count);
            if (isset($data['comment_list'])) {
                $dat = Json::decode($data['comment_list']);
                foreach ($dat['comment'] as $comment) {
                    $result[] = [
                        'nick_name' => $comment['nick_name'],
                        'content' => $comment['content'],
                        'is_elected' => $comment['is_elected'],
                        'del_flag' => $comment['del_flag'],
                        'openid' => $comment['fake_id'],
                    ];
                }
                if (count($dat['comment']) === 0 || count($dat['comment']) < $count) {
                    break;
                } else {
                    $begin += 50;
                }
            }
        }
        echo PHP_EOL;
        return $result;
    }
}
