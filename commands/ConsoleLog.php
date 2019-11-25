<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use Yii;
use yii\log\Logger;
use yii\log\Target;

/**
 * 在控制台输出日志信息
 * @author hightman <hightman@cloud-sun.com>
 * @author William Chan <root@williamchan.me>
 */
class ConsoleLog extends Target
{
    /**
     * @inheritdoc
     */
    public function getMessagePrefix($message)
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function export()
    {
        foreach ($this->messages as $message) {
            if ($message[1] === Logger::LEVEL_INFO && !($this->levels & Logger::LEVEL_TRACE) && !strncmp($message[2], 'yii\\', 4)) {
                continue;
            }
            if ($message[1] !== Logger::LEVEL_ERROR) {
                unset($message[4]);
            }
            fwrite(\STDERR, $this->formatMessage($message) . PHP_EOL);
        }
        fflush(\STDERR);
    }
}
