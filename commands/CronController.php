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

use app\models\Cron;
use Yii;

/**
 * 定时任务
 * @author William Chan <root@williamchan.me>
 */
class CronController extends Controller
{

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
     * 执行某一个定时任务
     * @param int $id
     */
    public function actionExec($id)
    {
        echo '[CRON] begin id=', $id, ', now=', date('Y/m/d H:i:s'), ' ...', PHP_EOL;
        $timeBegin = microtime(true);
        $cron = Cron::findOne($id);
        // 任务暂停不执行
        if ($cron->hasFlag(Cron::FLAG_PAUSE)) {
            echo "\tstatus is paused.", PHP_EOL;
            return 0;
        }
        // // 任务状态不对
        if ($cron->status !== Cron::STATUS_WAIT) {
            echo "\tstatus is not waiting.", PHP_EOL;
            return 0;
        }
        // 执行任务的时间没到
        if ($cron->time_exec > time()) {
            echo "\tnot the right time yet.", PHP_EOL;
            return 0;
        }
        $cron->status = Cron::STATUS_RUNING;
        $cron->save(false);
        // 循环制任务处理
        if ($cron->time_loop > 0) {
            $cron->time_exec = (int) (time() / 60) * 60 + $cron->time_loop;
        }
        $return = $this->execCron($cron);
        // return 遵循操作系统层面
        // 0 -> success
        // 1 -> warning
        // 2 -> error
        if ($return === 0) {
            $cron->removeFlag(Cron::FLAG_ERROR | Cron::FLAG_WARNING);
        } elseif ($return === 1) {
            $cron->removeFlag(Cron::FLAG_WARNING);
            $cron->addFlag(Cron::FLAG_ERROR);
        } elseif ($return === 2) {
            $cron->removeFlag(Cron::FLAG_ERROR);
            $cron->addFlag(Cron::FLAG_WARNING);
        }

        if ($cron->time_loop > 0) {
            $cron->status = Cron::STATUS_WAIT;
        } else {
            $cron->status = Cron::STATUS_DONE;
        }

        $cron->save(false);
        echo '[CRON] end time=', sprintf('%.4f', microtime(true) - $timeBegin), PHP_EOL;
    }

    /**
     * 执行一个定时任务
     * @param Cron $cron
     * @return void
     */
    private function execCron(Cron $cron)
    {
        $ret = 0;
        $timeBegin = microtime(true);
        $this->log($cron, PHP_EOL . 'BEGIN CRON #' . $cron->id . PHP_EOL, true);
        $this->log($cron, '+---------+---------------------+------+----------+------------------+' . PHP_EOL);
        $this->log($cron, '| Cron id | date now            | type | loop     | Load Average     |' . PHP_EOL);
        $this->log($cron, '+---------+---------------------+------+----------+------------------+' . PHP_EOL);
        $this->log($cron, sprintf(
            '| %-7s | %-19s | %-4d | %-8d | %-16s |' . PHP_EOL,
            $cron->id,
            date('Y/m/d H:i:s'),
            $cron->type,
            $cron->time_loop,
            implode(' ', sys_getloadavg())
        ));
        $this->log($cron, '+---------+---------------------+------+----------+------------------+' . PHP_EOL);
        switch ($cron->type) {
            case Cron::TYPE_SEND_TESTING_READY_TEMPLATE_NOTIFY:
                $ret = $this->exec('testing/notify/send-ready-notify ' . $cron->id, $cron->logPath);
                $ret = $ret['return'];
                break;
            case Cron::TYPE_SEND_TESTING_POINT_TEMPLATE_NOTIFY:
                $ret = $this->exec('testing/notify/send-point-notify ' . $cron->id, $cron->logPath);
                $ret = $ret['return'];
                break;
            case Cron::TYPE_SEND_COMMON_CUSTOMER_MESSAGE:
                $ret = $this->exec('wechat/send-customer ' . $cron->id, $cron->logPath);
                $ret = $ret['return'];
                break;
            case Cron::TYPE_SEND_COMMON_TEMPLATE_NOTIFY:
                $ret = $this->exec('wechat/send-template ' . $cron->id, $cron->logPath);
                $ret = $ret['return'];
        }
        $this->log($cron, PHP_EOL . 'time: ' . sprintf('%.4f', microtime(true) - $timeBegin) . PHP_EOL);
        $this->log($cron, 'Load Average: ' . implode(' ', sys_getloadavg()) . PHP_EOL);
        $this->log($cron, 'END CRON #' . $cron->id . PHP_EOL);

        return $ret;
    }

    /**
     * 输出日志
     * @param Cron $cron
     * @param array|string $output
     * @param bool $check
     * @return void
     */
    private function log($cron, $output, $check = false)
    {
        $filename = $cron->logPath;
        // 只保存 7 天日志
        if ($check && file_exists($filename) && filectime($filename) < time() - 60 * 60 * 7) {
            @unlink($filename);
        }
        if (is_array($output)) {
            $output = implode(PHP_EOL, $output);
        }
        @file_put_contents($filename, $output, FILE_APPEND);
    }

    /**
     * 执行一个命令行任务
     * string exec ( string $command [, array &$output [, int &$return_var ]] )
     * @see http://php.net/manual/en/function.exec.php
     * @param string $args
     * @param string $logPath
     * @return array
     */
    private function exec($args, $logPath)
    {
        $cmd = './yii ' . $args . ' >> ' . $logPath . ' 2>&1';
        $output = [];
        $return = 0;
        exec($cmd, $output, $return);
        return ['return' => $return, 'output' => $output];
    }
}
