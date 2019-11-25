<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\components\sms;

use app\components\Muggle;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * 短信发送组件
 * @author hightman <hightman@cloud-sun.com>
 */
class Qcloud extends BaseObject implements SmsInterface
{
    /**
     * @var string 应用 id
     */
    public $appId = '';

    /**
     * @var string 应用密钥
     */
    public $appKey = '';

    /**
     * @var array 短信模板列表
     */
    public $templates = [];
    private $_error;

    public function init()
    {
        parent::init();
        if (!isset($this->templates['verify'])) {
            $this->templates['verify'] = '【海鳗插件】{1} 为您的验证码，十分钟内有效。如非本人操作，请忽略本短信。';
        }
    }

    /**
     * @inheritdoc
     */
    public function getLastError()
    {
        return $this->_error;
    }

    /**
     * @inheritdoc
     */
    public function sendVerifyCode($phone, $code)
    {
        $data = [];
        if (substr($phone, 0, 1) === '+') {
            $ncl = $this->nationCodeLength(substr($phone, 1));
            $data['tel']['nationcode'] = substr($phone, 1, $ncl);
            $phone = substr($phone, $ncl + 1);
        } elseif (substr($phone, 0, 2) === '00') {
            $ncl = $this->nationCodeLength(substr($phone, 2));
            $data['tel']['nationcode'] = substr($phone, 2, $ncl);
            $phone = substr($phone, $ncl + 2);
        } else {
            $data['tel']['nationcode'] = '86';
        }
        $data['tel']['phone'] = $phone;
        $data['type'] = '0';
        $data['msg'] = str_replace('{1}', $code, $this->templates['verify']);
        $data['sig'] = $this->generateSignature($data['tel']['phone']);

        $response = Muggle::httpRequest('POST_JSON', $this->getApiUrl(), $data);
        if ($response === false || ($data = Json::decode($response)) === null) {
            $this->_error = 'Request failed';
            return false;
        } elseif (!isset($data['result']) || $data['result'] !== '0') {
            $this->_error = isset($data['errmsg']) ? $data['errmsg'] : 'Unknown error';
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    private function getApiUrl()
    {
        return 'https://yun.tim.qq.com/v3/tlssmssvr/sendsms?sdkappid=' . $this->appId . '&random=' . mt_rand(1000, 9999);
    }

    /**
     * @param string $phone
     * @return string
     */
    private function generateSignature($phone)
    {
        return md5($this->appKey . $phone);
    }

    /**
     * @param string $code
     * @return int
     */
    private function nationCodeLength($code)
    {
        // 国际区码：1位=1, 4位=18xx/19xx/672x,
        if (substr($code, 0, 1) === '1') {
            return 1;
        } elseif (substr($code, 0, 3) === '672') {
            return 4;
        } else {
            $first2 = substr($code, 0, 2);
            if ($first2 === '18' || $first2 === '19') {
                return 4;
            } else {
                $nation3 = [
                    '22' => true, '23' => true, '24' => true, '25' => true, '26' => true, '29' => true,
                    '50' => true, '59' => true, '67' => true, '68' => true,
                    '85' => true, '88' => true, '96' => true, '97' => true,
                ];
                return isset($nation3[$first2]) ? 3 : 2;
            }
        }
    }
}
