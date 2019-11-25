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

use app\components\QueueJob;
use Yii;
use yii\db\Exception;
use yii\di\Instance;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * 队列任务工具
 * @author William Chan <root@williamchan.me>
 * @author hightman <hightman@cloud-sun.com>
 */
class QueueController extends Controller
{
    /**
     * @var \app\components\QueueJob|string 队列组件
     */
    public $queue = 'queue';

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        $this->queue = Instance::ensure($this->queue, QueueJob::class);
        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'queue';
        return $options;
    }

    /**
     * 循环处理队列任务
     * 允许运行多个实例以提高密集响应速度
     * @param int $index 序号
     */
    public function actionStart($index = null)
    {
        $pid = $this->processId($index);
        if ($pid !== 0) {
            Yii::info('queue locked. [' . $pid . ']');
            return;
        }
        // main loop
        $this->queue->redis->dataTimeout = 3598;
        Yii::info('queue worker started.');
        while (true) {
            try {
                while ($data = $this->queue->pop()) {
                    if (!isset($data['type'])) {
                        continue;
                    }
                    Yii::info('handling job: ' . $data['type'] . ' ...');
                    $this->queue->handle($data);
                }
            } catch (Exception $e) {
                Yii::$app->db->close();
                $ref = new \ReflectionClass($this->queue->redis);
                $pro = $ref->getProperty('_socket');
                $pro->setAccessible(true);
                @stream_socket_shutdown($pro->getValue($this->queue->redis), STREAM_SHUT_RDWR);
                $pro->setValue($this->queue->redis, false);
                Yii::error('queue restarted: ' . $e->getMessage());
                sleep(2);
            }
        }
    }

    /**
     * 停止队列任务
     * @param string $name queue name. 'all', 'queue', 'queue2' or 'queue3', ...etc
     * Determine which queue will be terminated.
     */
    public function actionStop($name = 'all')
    {
        $output = null;
        if ($name === 'all') {
            exec("ps -ef | grep 'yii queue/start' | grep -v grep | awk '{print $2}'", $output);
        } elseif ($name === 'queue') {
            exec("ps -ef | grep 'yii queue/start [^(--queue=.*)]' | grep -v grep | awk '{print $2}'", $output);
        } else {
            exec("ps -ef | grep 'yii queue/start --queue=$name' | grep -v grep | awk '{print $2}'", $output);
        }
        foreach ($output as $item) {
            exec('kill -TERM ' . (int) $item);
            $this->stdout('> pid ' . $item . ' killed.' . PHP_EOL, Console::FG_GREEN);
        }
        $this->stdout('# ' . ($name === 'all' ? ($name . ' queue') : $name) . ' terminated.' . PHP_EOL, Console::FG_GREEN);
    }

    /**
     * 查看任务数量
     */
    public function actionInfo()
    {
        $num = $this->queue->redis->executeCommand('LLEN', [$this->queue->name]);
        Yii::info('number of jobs: ' . $num);
    }

    /**
     * 查看DLQ数量
     */
    public function actionDlq()
    {
        $num = Yii::$app->redis2->executeCommand('LLEN', ['DLQ-' . $this->queue->name]);
        Yii::info('number of dlq: ' . $num);
    }

    /**
     * 添加任务
     * @param string $data JSON 格式的数据字符串
     */
    public function actionPush($data)
    {
        $data = Json::decode($data);
        if (!is_array($data) || !isset($data['type'])) {
            return Yii::error('job data must contain a "type" field.');
        }
        $this->queue->push($data);
        Yii::info('job added.');
    }
}
