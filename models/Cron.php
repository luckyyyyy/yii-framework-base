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
namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;

/**
 *
 * 创建定时任务：
 * 任务类型：客服消息-群发所有用户
 * 发送内容：图文/文本/XX
 * 发送公众号：*
 * 首次时间：23:00
 * 周期执行：1天
 *
 * 创建定时任务：
 * 任务类型：客服消息-营销
 * 发送内容：图文/文本/XX
 * 发送公众号：*
 * 发送覆盖用户TAG：玩具/科技
 * 首次时间：23:00
 * 周期执行：1天
 *
 * 创建定时任务：
 * 任务类型：众测模板消息-剩余积分提醒
 * 发送内容：模板消息
 * 发送公众号：众测
 * 首次时间：23:00
 * 众测期数：40

 * 创建定时任务：
 * 任务类型：用户促活提醒-点击菜单后2次送达
 * 发送内容：图文/文本/XX
 * 发送公众号：*
 * 首次时间：23:00
 * 周期执行：5分钟
 *
 */

/**
 * 定时任务表
 *
 * @property int $id
 * @property int $flag 标记
 * @property int $type 任务类型
 * @property int $status 状态
 * @property int $identity_id 创建者
 * @property int $time_exec 下一次运行时间
 * @property int $time_loop 循环间隔时间 0是一次性任务
 * @property array $extra 扩展配置
 *
 * @property-read Identity $identity 通行证
 * @property-read bool $isLoop 是否是周期任务
 * @property-read bool $isError 任务中是否遇到过错误
 * @property-read bool $isWarning 任务中是否遇到过警告
 * @property-read bool $isPause 是否手动暂停
 * @property-read bool $isSuspend 任务是否挂起（循环执行的任务出错后才可能出现）
 *
 * @author William Chan <root@williamchan.me>
 */
class Cron extends ActiveRecord
{
    const FLAG_PAUSE = 0x1; // 任务被手动暂停
    const FLAG_ERROR = 0x4; // 任务途中遇到过错误
    const FLAG_WARNING = 0x8; // 任务途中遇到过告警

    const STATUS_WAIT = 1; // 任务等待中
    const STATUS_RUNING = 2; // 任务运行中
    const STATUS_DONE = 3; // 任务已完成
    const STATUS_SUSPEND = 4; // 因为安全机制 遇到严重错误任务被挂起

    /**
     * 众测开始前通知（模板）
     * TYPE_SEND_TESTING_READY_TEMPLATE_NOTIFY
     * {
     *     extra": {
     *         "id":"1",
     *         "data": {
     *             "first": {
     *                 "value":"新的众测即将开始了哦。\n",
     *                 "color":"#173177"
     *             },
     *             "keyword1": {
     *                 "value":"iPhone",
     *                 "color":"#173177"
     *             },
     *             "keyword2": {
     *                 "value":"2018年03月02日00:00:00",
     *                 "color":"#173177"
     *             },
     *             "remark": {
     *                 "value":"\n点击此消息即可查看详情，赶紧提前召唤小伙伴参与吧！","color":"#888"
     *             }
     *         }
     *     }
     * }
     */
    const TYPE_SEND_TESTING_READY_TEMPLATE_NOTIFY = 1;

    /**
     * 众测结束前剩余积分大于0通知（模板）
     * TYPE_SEND_TESTING_POINT_TEMPLATE_NOTIFY
     * {
     *     extra": {
     *         "id":"1",
     *         "data": {
     *             "first": {
     *                 "value":"本期众测您还有机会获得产品，不如来使用了吧。\n",
     *                 "color":"#173177"
     *             },
     *             "keyword1": {
     *                 "value":"您还有剩余的机会未使用",
     *                 "color":"#173177"
     *             },
     *             "keyword2": {
     *                 "value":"点击此消息参与",
     *                 "color":"#173177"
     *             },
     *             "remark": {
     *                 "value":"\n众测即将结束，抓紧时间哦。","color":"#888"
     *             }
     *         }
     *     }
     * }
     */
    const TYPE_SEND_TESTING_POINT_TEMPLATE_NOTIFY = 2;

    /**
     * 发送一个服务信息 2天内的用户
     * TYPE_COMMON_SEND_CUSTOMER_MESSAGE
     * {
     *     extra": {
     *         "scenario": "A",
     *         "media_id":"1",
     *         "message":"内容内容",
     *     }
     * }
     */
    const TYPE_SEND_COMMON_CUSTOMER_MESSAGE = 3;

    /**
     * 发送通用模板的信息
     * TYPE_SEND_COMMON_CUSTOMER_MESSAGE
     * {
     *     extra": {
     *         "scenario": "A",
     *         "template": "xxx",
     *         "url": "https://www.baidu.com",
     *         "data": [
     *             { "key": "first", "value":"first\n", "color":"#173177" },
     *             { "key": "keyword1", "value":"keyword1", "color":"#173177" },
     *             { "key": "keyword2", "value":"keyword2", "color":"#173177" },
     *             { "key": "remark", "value":"remark\n", "color":"#888888" },
     *         ]
     *     }
     * }
     */
    const TYPE_SEND_COMMON_TEMPLATE_NOTIFY = 4;

    use FlagTrait;
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cron';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['extra', 'time_exec', 'type'], 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $attributes = ['type', 'time_loop', 'extra', 'time_exec'];
        return [self::SCENARIO_DEFAULT => $attributes];
    }

    /**
     * 自动将 extra 转换为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        if (in_array($name, ['extra'])) {
            $value = $this->getAttribute($name);
            if (!is_array($value)) {
                $value = Json::decode($value);
                $value = is_array($value) ? $value : [];
                $this->setAttribute($name, $value);
            }
            return $value;
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->status = self::STATUS_WAIT;
            $this->identity_id = Yii::$app->user->id;
        }
        $value = $this->getAttribute('extra');
        if (is_array($value)) {
            $this->setAttribute('extra', Json::encode($value));
        }
        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if (is_string($value) && $name === 'time_exec') {
            $value = strtotime($value);
        }
        parent::__set($name, $value);
    }

    /**
     * 关联帐号
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id']);
    }

    /**
     * 日志路径
     * @return string
     */
    public function getLogPath()
    {
        return Yii::getAlias('@app/logs/cron/cron_' . $this->id . '.log');
    }

    /**
     * 权限标记定义
     * @return array
     */
    public static function flagOptions()
    {
        return [
            // self::FLAG_PAUSE => '任务暂停', // 任务被手动暂停
            self::FLAG_ERROR => '运行过程中有错误', // 任务途中遇到过错误
            self::FLAG_WARNING => '运行过程中有警告', // 任务途中遇到过告警
        ];
    }
}
