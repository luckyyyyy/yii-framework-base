<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\components;

use Yii;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\base\BaseObject;
use yii\di\Instance;
use yii\redis\Connection;

/**
 * 任务队列管理
 * @author William Chan <root@williamchan.me>
 */
class QueueJob extends BaseObject
{
    use LogTrait;

    const MAX_TRY_COUNT = 3; // 最大尝试次数

    /**
     * @var \yii\redis\Connection|string|array the Redis object or the application component ID of the Redis.
     */
    public $redis = 'redis';

    /**
     * @var string 队列名称
     */
    public $name = 'queue';

    /**
     * 初始化 redis 组件
     */
    public function init()
    {
        parent::init();
        if ($this->redis !== false) {
            $this->redis = Instance::ensure($this->redis, Connection::class);
        }
    }

    /**
     * 添加一个任务
     * @param array $data 任务数据
     */
    public function push($data)
    {
        if (!is_array($data) || !isset($data['type'])) {
            throw new InvalidParamException('Invalid queue job data.');
        }
        if ($this->redis === false) {
            return $this->handle($data);
        }
        try {
            $this->redis->executeCommand('LPUSH', [$this->name, serialize($data)]);
        } catch (Exception $e) {
            $session = Yii::$app->get('session', false);
            if ($session !== null) {
                $session->setFlash('error', $e->getMessage());
            }
        }
    }

    /**
     * 弹出一个任务
     * @param bool $blocking 无任务时是否阻塞
     * @return array|null 任务数据
     */
    public function pop($blocking = true)
    {
        if ($blocking) {
            $res = $this->redis->executeCommand('BRPOP', [$this->name, 0]);
        } else {
            $res = $this->redis->executeCommand('RPOP', [$this->name]);
        }
        if (is_array($res) && isset($res[1])) {
            $data = @unserialize($res[1]);
            if (isset($data['type'])) {
                return $data;
            }
        }
        return null;
    }

    /**
     * 任务处理
     * @param array $data 任务数据
     */
    public function handle($data)
    {
        try {
            switch ($data['type']) {
                case 'sendCustomer': // 发送客服消息
                    Yii::$app->get('wechatApp')
                        ->setScenario($data['scenario'])
                        ->sendCustomer($data['openid'], $data['data'], $data['msgType']);
                    break;
                case 'downloadMedia':
                    Yii::$app->get('wechatApp')
                        ->setScenario($data['scenario'])
                        ->fetchMediaFile($data['media_id'], $data['path']);
                    break;
                case 'sendTemplate': // 发模板消息
                    Yii::$app->get('wechatApp')
                        ->setScenario($data['scenario'])
                        ->sendTemplate($data['openid'], $data['url'], $data['template'], $data['data']);
                    break;
                case 'migrateWechatAvatar': // 迁移微信头像到阿里云
                    \app\models\Identity::migrateAvatar($data['id']);
                    break;
                // case 'updateTestingAnswerCount': // 问卷和选项累积加1
                //     \app\modules\testing\api\controllers\Queue::updateAnswerCount($data['log_id']);
                //     break;
                // case 'updateTestingRank': // 更新众测排行榜
                //     \app\modules\testing\api\controllers\Queue::updateTestingRank($data['id']);
                //     break;
                // case 'testingReferral': // 给招募用户发送提醒
                //     \app\modules\testing\api\controllers\Queue::referral($data['activity_id'], $data['identity_id'], $data['referral_id']);
                //     break;
            }
        } catch (\Exception $e) {
            // 任务执行失败，再次推送，如果错误次数大于限定值，就进入 Dead Letter Queue
            $data['__MAX_TRY_COUNT__'] = isset($data['__MAX_TRY_COUNT__']) ? ++$data['__MAX_TRY_COUNT__'] : 1;
            if ($data['__MAX_TRY_COUNT__'] >= self::MAX_TRY_COUNT) {
                // 消息转移到 DLQ / 手工干预 或者 @todo 自动重试
                try {
                    $dat = serialize(['data' => $data, 'time' => time()]);
                    Yii::$app->redis2->executeCommand('LPUSH', ['DLQ-' . $this->name, $dat]);
                    $this->log($e->getMessage() . ' - ' . $dat);
                } catch (Exception $e) {
                    $session = Yii::$app->get('session', false);
                    if ($session !== null) {
                        $session->setFlash('error', $e->getMessage());
                    }
                }
            } else {
                $this->push($data);
            }
        }
    }

    /**
     * 添加发送客服消息任务
     * @param string $scenario 场景
     * @param string $openid 发送目标
     * @param mixed $data 发送数据
     * @param string $type 消息类型
     */
    public function pushSendCustomer($scenario, $openid, $data, $type)
    {
        $this->push([
            'scenario' => $scenario,
            'type' => 'sendCustomer',
            'openid' => $openid,
            'data' => $data,
            'msgType' => $type,
        ]);
    }

    /**
     * 添加发送模板消息任务
     * @param string $scenario 场景
     * @param string $openid 发送目标
     * @param string $url 点击链接
     * @param string $template 模板 ID
     * @param array $data 发送数据
     */
    public function pushSendTemplate($scenario, $openid, $url, $template, $data)
    {
        $this->push([
            'scenario' => $scenario,
            'type' => 'sendTemplate',
            'openid' => $openid,
            'url' => $url,
            'template' => $template,
            'data' => $data,
        ]);
    }

    /**
     * 处理推荐
     * @param int $activity_id 活动ID
     * @param int $identity_id 参与用户
     * @param int $eferral_id 推介用户（发给他信息）
     */
    public function pushTestingReferral($activity_id, $identity_id, $referral_id)
    {
        $this->push([
            'type' => 'testingReferral',
            'activity_id' => $activity_id,
            'identity_id' => $identity_id,
            'referral_id' => $referral_id,
        ]);
    }

    /**
     * 迁移微信用户头像
     * @param int $id
     * @return void
     */
    public function pushMigrateWechatAvatar($id)
    {
        $this->push([
            'type' => 'migrateWechatAvatar',
            'id' => $id,
        ]);
    }

    /**
     * 从微信临时文件上传到阿里云
     * @param string $scenario
     * @param string $media_id
     * @param string $path
     * @return void
     */
    public function pushDownloadMedia($scenario, $media_id, $path)
    {
        $this->push([
            'type' => 'downloadMedia',
            'media_id' => $media_id,
            'scenario' => $scenario,
            'path' => $path,
        ]);
    }

}
