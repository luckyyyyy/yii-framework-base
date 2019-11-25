<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 *
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 */
namespace app\commands;

use app\components\Muggle;
use app\models\wechat\WechatQrcode;
use app\models\wechat\WechatFormid;
use app\models\Cron;
use Yii;

/**
 * 周期工具
 * 配合 crontab 执行
 * use: * * * * * (cd /data/api; ./yii cycle >> runtime/cycle.log 2>&1)
 * @author hightman <hightman@cloud-sun.com>
 * @author William Chan <root@williamchan.me>
 */
class CycleController extends Controller
{
    /**
     * @var bool 是否开启日志追加模式
     */
    public $appendLogging = false;

    /**
     * 切换工作目录
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        chdir(Yii::getAlias('@app'));
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'appendLogging';
        return $options;
    }

    /**
     * 检查停用
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!in_array($action->id, ['stop', 'start']) && file_exists($this->getStopFile())) {
            Yii::warning('manual paused, Please run `start` comamnd to resume.');
            return false;
        }
        return parent::beforeAction($action);
    }

    /**
     * 入口
     * 每分钟执行一次
     */
    public function actionIndex()
    {
        Yii::info('begin');
        $now = time();

        // check queues
        $this->actionQueue();

        // run corn
        $this->actionCron();

        // purge expired wechat data, every day=05:00
        if (date('Hi', $now) === '0500') {
            echo '  > clearing wechat expired data ... ';
            $wechat = Yii::$app->wechatApp;
            foreach ($wechat->scenarios as $scenario) {
                Yii::$app->db->tablePrefix = $scenario['prefix'] . '_';
                try {
                    $count = WechatQrcode::purge();
                    echo $wechat->scenario, ' qrcode ', $count, ' deleted', PHP_EOL;
                } catch (\Exception $e) {
                    echo $wechat->scenario,' does not qrcode table, skipped.', PHP_EOL;
                }
                try {
                    $count = WechatFormid::purge();
                    echo $wechat->scenario, ' formid ', $count, ' deleted', PHP_EOL;
                } catch (\Exception $e) {
                    echo $wechat->scenario,' does not formid table, skipped.', PHP_EOL;
                }
            }
        }

        // // wechat stat C, every day=08:00
        // if (date('Hi', $now) > '0800') {
        //     $this->exec('wechat/stat C');
        // }

        // // wechat stat B, every day=08:00
        // if (date('Hi', $now) > '0800') {
        //     $this->exec('wechat/stat B');
        // }

        // // wechat stat A, every day=10:00
        // if (date('Hi', $now) > '1000') {
        //     $this->exec('wechat/stat A');
        // }

        // 获取文章阅读量
        // $this->exec('wechat/mp A');





        Yii::info('end');
    }

    /**
     * 检查队列 2个垃圾队列4个重要队列
     * @param string $cmd 命令 (stop|start)
     */
    public function actionQueue($cmd = 'start')
    {
        $options = ['', '', ' --queue=queue2', ' --queue=queue2', ' --queue=queue2', ' --queue=queue2', ' --queue=queue3'];
        for ($i = 0; $i < count($options); $i++) {
            $suffix = $i + 1;
            echo '  > checking queue[', $suffix, '] ... ';
            $pid = $this->processId($suffix, 'queue');
            if ($pid !== 0) {
                if ($cmd === 'stop') {
                    $output = null;
                    exec("ps -ef | grep 'yii queue.*$suffix$' | grep -v grep | awk '{print $2}' | grep -w $pid", $output);
                    if (current($output)) {
                        exec('kill -TERM ' . $pid);
                        echo 'pid ' . $pid . ' killed';
                    } else {
                        $output = null;
                        exec("ps -ef | grep 'yii queue.*$suffix$' | grep -v grep | awk '{print $2}'", $output);
                        exec('kill -TERM ' . (int) current($output));
                        echo 'pid ' . current($output) . ' force killed';
                    }
                    /**
                     * 避免看不懂 列出来吧 一般情况下还是推荐 kill -15
                     * 记住下面这个网站 或者命令行里面 man kill
                     * @see https://www.freebsd.org/cgi/man.cgi?query=kill
                     * 1      HUP (hang up)
                     * 2      INT (interrupt)
                     * 3      QUIT (quit)
                     * 6      ABRT (abort)
                     * 9      KILL (non-catchable, non-ignorable kill)
                     * 14     ALRM (alarm clock)
                     * 15     TERM (software termination signal)
                     */
                } else {
                    echo 'running [' . $pid . ']';
                }
            } else {
                if ($cmd === 'stop') {
                    echo 'stopped';
                } else {
                    $args = 'queue/start' . $options[$i] . ' ' . $suffix;
                    $log = 'queue' . $suffix . '.log';
                    $this->exec($args, $log);
                    echo 'started';
                }
            }
            echo PHP_EOL;
        }
    }

    /**
     * 执行定时任务
     */
    public function actionCron()
    {
        echo '  > checking run cron ... ', PHP_EOL;
        $crons = Cron::find()
            ->where(['status' => Cron::STATUS_WAIT])
            ->andWhere(['<', 'time_exec', time()])
            ->all();
        foreach ($crons as $cron) {
            echo '  > push cron exec #', $cron->id, ' ... ', PHP_EOL;
            $this->exec('cron/exec '. $cron->id);
        }
    }

    /**
     * 暂停
     */
    public function actionStop()
    {
        touch($this->getStopFile());
        Yii::info('cycle engine paused.');
    }

    /**
     * 恢复
     */
    public function actionStart()
    {
        unlink($this->getStopFile());
        Yii::info('cycle engine resumed.');
    }

    /**
     * 获取暂停标记文件路径
     */
    private function getStopFile()
    {
        return Yii::getAlias('@app/runtime/cycle.stop');
    }

    /**
     * 执行一个命令行任务
     * 2>&1是 stderr重定向到stdout的意思 这问题06年才搞明白 18年又被问到
     * @see http://php.net/manual/en/function.exec.php
     * @param string $args
     * @param string|null $log
     * @return void
     */
    private function exec($args, $log = null)
    {
        if ($log === null) {
            $log = ' > /dev/null';
        } else {
            $log = ' >' . ($this->appendLogging ? '>' : '') . ' runtime/logs/' . $log;
        }
        $cmd = './yii ' . $args . $log . ' 2>&1 &';
        exec($cmd);
    }
}
