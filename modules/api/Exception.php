<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api;

use yii\web\HttpException;

/**
 * API 响应异常
 *
 * @author William Chan <root@williamchan.me>
 */
class Exception extends HttpException
{
    /**
     * @var mixed Error data
     */
    public $data;

    /**
     * Constructor.
     * @param mixed $data error data
     * @param string $message error message
     * @param integer $code error code (httpStatus * 100 + code % 99)
     */
    public function __construct($data = null, $message = null, $code = 40000)
    {
        if ($code >= 10000) {
            $status = intval($code / 100);
            $code = $code % 100;
        } elseif ($code >= 100 && $code < 600) {
            $status = $code;
            $code = 0;
        } else {
            $status = 400;
        }
        $this->statusCode = $status;
        $this->data = $data;
        if ($message === null) {
            $message = $this->getName();
        }
        parent::__construct($status, $message, $code);
    }
}
