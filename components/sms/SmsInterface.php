<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\components\sms;

/**
 * 短信发送接口定义
 *
 * @author hightman <hightman@cloud-sun.com>
 */
interface SmsInterface
{
    /**
     * @return string 最后的出错信息
     */
    public function getLastError();

    /**
     * @param string $phone 目标手机号
     * @param string $code 验证码
     * @return bool
     */
    public function sendVerifyCode($phone, $code);
}
