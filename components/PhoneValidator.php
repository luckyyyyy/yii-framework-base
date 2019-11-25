<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\validators\Validator;

/**
 * 手机号码验证器
 * @author William Chan <root@williamchan.me>
 */
class PhoneValidator extends Validator
{
    /**
     * @var string 手机号码字段名称，若设置 [[phoneValue]] 则后者优先，如果都未设置则默认为 'phone' 字段。
     * @see phoneValue
     */
    public $phoneAttribute;

    /**
     * @var mixed 手机号码常量，如果和 [[phoneAttribute]] 同时设置，则此属性优先。
     * @see compareAttribute
     */
    public $phoneValue;

    /**
     * @var int 验证码长度
     */
    public $length = 6;

    /**
     * @var int 发验证码冷却
     */
    public $delayTime = 60;

    /**
     * @var int 几次不对后就清除
     */
    public $testLimit = 4;

    /**
     * @var string|\yii\caching\Cache 缓存组件
     */
    public $cache = 'cache2';

    /**
     * @var string 缓存名称前缀
     */
    public $cachePrefix = 'phone.code.';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (is_string($this->cache)) {
            $this->cache = Yii::$app->get($this->cache);
            if (!$this->cache instanceof Cache) {
                throw new InvalidConfigException('The "cache" property must be valid cache component ID.');
            }
        }
        if ($this->message === null) {
            $this->message = '{attribute}不正确';
        }
    }

    /**
     * @inheritdoc
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($this->phoneValue === null) {
            $phoneAttribute = $this->phoneAttribute === null ? 'phone' : $this->phoneAttribute;
            $phoneValue = $this->$phoneAttribute;
        } else {
            $phoneValue = $this->phoneValue;
        }
        $code = $this->fetchCachedCode($phoneValue);
        if ($code !== $value) {
            $this->addError($model, $attribute, $this->message);
        }
    }

    /**
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        if ($this->phoneValue === null) {
            throw new InvalidConfigException('PhoneValidator::phoneValue must be set.');
        }
        if (Muggle::isDebugEnv()) {
            return null;
        }
        $code = $this->fetchCachedCode($this->phoneValue);
        return $code === $value ? null : [$this->message, []];
    }

    /**
     * @return int 还需多少秒才能发送
     */
    public function getSendLeft()
    {
        $time = $this->cache->get($this->getLimitKey());
        if ($time !== false) {
            $delay = $this->delayTime - (time() - $time);
            if ($delay > 0) {
                return $delay;
            }
        }
        return 0;
    }

    /**
     * 发送验证码
     * @param string $phone
     * @param string $sign 短信签名
     * @return string 成功返回 OK，失败返回错误原因
     */
    public function sendCode($phone, $sign)
    {
        $sms = Yii::$app->get('sms');
        $code = sprintf('%0' . $this->length . 'd', random_int(0, pow(10, $this->length) - 1));
        $sms->setScenario('COMMON_VERIFY', $sign);
        if ($sms->sendVerifyCode($phone, $code) !== true) {
            return $sms->getLastError();
        }
        $this->cache->set($this->getValueKey($phone), ['code' => $code, 'tries' => 0], $this->delayTime * 10);
        $this->cache->set($this->getLimitKey(), time(), $this->delayTime);
        return 'OK';
    }

    /**
     * 发送短信
     * @param int $phone 手机号
     * @param array $params 发送短信的变量
     * @param string $sign 短信签名
     * @param string $scenario
     * @return string
     */
    public function sendMsg($phone, $params, $sign, $scenario)
    {
        $sms = Yii::$app->get('sms');
        $code = sprintf('%0' . $this->length . 'd', random_int(0, pow(10, $this->length) - 1));
        $sms->setScenario($scenario, $sign);
        if ($sms->sendMsg($phone, $params) !== true) {
            return $sms->getLastError();
        }
        $this->cache->set($this->getValueKey($phone), ['code' => $code, 'tries' => 0], $this->delayTime * 10);
        $this->cache->set($this->getLimitKey(), time(), $this->delayTime);
        return 'OK';
    }



    /**
     * @param string $phone
     * @return string
     */
    private function fetchCachedCode($phone)
    {
        $key = $this->getValueKey($phone);
        $data = $this->cache->get($key);
        if ($data === false || !isset($data['code'], $data['tries'])) {
            return false;
        }
        $data['tries']++;
        if ($data['tries'] >= $this->testLimit) {
            $this->cache->delete($key);
        } else {
            $this->cache->set($key, $data, $this->delayTime * (10 - $data['tries']));
        }
        return $data['code'];
    }

    /**
     * @param string $value
     * @return string
     */
    private function getValueKey($value)
    {
        return $this->cachePrefix . $value;
    }

    /**
     * @return string
     */
    private function getLimitKey()
    {
        $ip = Muggle::clientIp();
        return $this->cachePrefix . 'limit_' . $ip;
    }
}
