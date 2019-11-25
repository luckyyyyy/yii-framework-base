<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components\sms;

use app\components\Muggle;
use app\components\Html;
use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * 短信发送组件
 *
 * @author William Chan <root@williamchan.me>
 */
class Aliyun extends BaseObject implements SmsInterface
{

    /**
     * @var string API 密钥信息
     */
    public $accessKeyId;
    public $accessKeySecret;

    /**
     * @var array 场景列表
     */
    public $scenarios = [];

    /**
     * @var string api接口地址
     */
    public $apiUrl = 'https://dysmsapi.aliyuncs.com/';

    /**
     * @var string Region Id
     */
    public $regionId = 'cn-hangzhou';

    private $_code;
    private $_scenario;
    private $_error;
    private $_signName;

    /**
     * 设置发送的短信场景
     * @param string $scenario
     * @param string $signName
     * @return static
     */
    public function setScenario($scenario, $signName = null)
    {
        if (isset($this->scenarios[$scenario])) {
            $this->_code = $this->scenarios[$scenario];
            if ($signName) {
                $this->signName = $signName;
            }
        }
        return $this;
    }

    /**
     * 获取当前场景
     * @return string
     */
    public function getScenario()
    {
        return $this->_scenario;
    }

    /**
     * 设置签名（必须通过阿里云验证）
     * @see https://dysms.console.aliyun.com/dysms.htm?#/develop/sign
     * @return void
     */
    public function setSignName($signName)
    {
        $this->_signName = $signName;
    }

    /**
     * 获取当前签名
     * @return string
     */
    public function getSignName()
    {
        return $this->_signName;
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
        if (substr($phone, 0, 1) === '+') {
            $phone = '00' . substr($phone, 1);
        }
        $bizParams = [
            'PhoneNumbers' => $phone,
            'SignName' => $this->signName,
            'TemplateCode' => substr($phone, 0, 2) === '00' ? $this->_code['outside'] : $this->_code['internal'],
        ];
        // 业务可选项
        $bizParams['TemplateParam'] = Json::encode(['code' => $code]);
        // $data['SmsUpExtendCode']
        // $data['OutId]
        $signature = $this->buildSignature($bizParams);


        try {
            $res = Muggle::guzzleHttpRequest('GET', $this->apiUrl . '?' . $signature);
            $raw = $res->getBody()->getContents();
            $data = Json::decode($raw);
            if (!isset($data['Code']) || $data['Code'] !== 'OK') {
                if ($data && isset($data['Message'])) {
                    $this->_error = $data['Message'];
                } else {
                    $this->_error = $raw;
                }
            } else {
                return true;
            }
        } catch (\Exception $e) {
            if (isset($res) && $res->error) {
                $this->_error = $e->getMessage();
            } else {
                $this->_error = 'timeout or unknown network error.';
                Yii::error('[sms] timeout or unknown network error.');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function sendMsg($phone, $params = null)
    {
        if (substr($phone, 0, 1) === '+') {
            $phone = '00' . substr($phone, 1);
        }
        $bizParams = [
            'PhoneNumbers' => $phone,
            'SignName' => $this->signName,
            'TemplateCode' => substr($phone, 0, 2) === '00' ? $this->_code['outside'] : $this->_code['internal'],
        ];
        // 业务可选项
        $bizParams['TemplateParam'] = $params ? Json::encode($params) : '{}';
        // $data['SmsUpExtendCode']
        // $data['OutId]
        $signature = $this->buildSignature($bizParams);

        try {
            $res = Muggle::guzzleHttpRequest('GET', $this->apiUrl . '?' . $signature);
            $raw = $res->getBody()->getContents();
            $data = Json::decode($raw);
            if (!isset($data['Code']) || $data['Code'] !== 'OK') {
                if ($data && isset($data['Message'])) {
                    $this->_error = $data['Message'];
                } else {
                    $this->_error = $raw;
                }
            } else {
                return true;
            }
        } catch (\Exception $e) {
            if (isset($res) && $res->error) {
                $this->_error = $e->getMessage();
            } else {
                $this->_error = 'timeout or unknown network error.';
                Yii::error('[sms] timeout or unknown network error.');
            }
        }
    }

    /**
     * 签名算法使用了阿里云的POP协议
     * @see https://help.aliyun.com/document_detail/56189.html
     * @param array $bizParams 业务参数
     * @param string $method API 请求方式 默认 GET
     * @return string
     */
    private function buildSignature($bizParams, $method = 'GET')
    {
        // 1st 生成待签名数组
        $params = [
            'AccessKeyId' => $this->accessKeyId,
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Format' => 'JSON',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce' => uniqid(random_int(1, 0xffff), true), // 这个是防止 replay attacks 的
        ];
        $params = array_merge($params, $bizParams, [
            'Action' => 'SendSms',
            'Version' => '2017-05-25',
            'RegionId' => $this->regionId,
        ]);

        // 2nd 生成待签名字符串
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $this->encode($key) . '=' . $this->encode($value);
        }
        $query = implode('&', $parts);

        // 3rd 生成签名
        // @fixme accessSecret 后面要加个 & 文档要求。
        $signature = base64_encode(hash_hmac('sha1', $method . '&' . urlencode('/') . '&' . $this->encode($query), $this->accessKeySecret . '&', true));
        $signature = $this->encode($signature);

        // 4th 拼接签名
        $query = 'Signature=' . $signature . '&' . $query;
        return $query;
    }

    /**
     * 阿里云POP的编码要求
     * + => %20
     * * => %2a
     * %7e => ~
     *
     * @param string $string
     * @return string
     */
    private function encode($string)
    {
        return str_replace('%7E', '~', rawurlencode($string));
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
