<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;

/**
 * 简易日志公用代码
 * @author William Chan <root@williamchan.me>
 */
trait LogTrait
{
    /**
     * @param string $msg logging message
     */
    protected function log($msg)
    {
        $file = null;
        if (property_exists($this, 'logFile')) {
            $file = $this->logFile;
        }
        if ($file !== '/dev/null') {
            if (!$file) {
                $file = '@app/logs/' . get_class() . '.log';
            }
            @file_put_contents(Yii::getAlias($file), date('Y/m/d H:i:s') . "\t" . Muggle::clientIp()
                . "\t" . $msg . "\r\n", FILE_APPEND);
        }
    }
}
