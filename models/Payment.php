<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\components\Html;
use app\components\Muggle;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Url;

// TODO 功能暂未用到
/**
 * 支付订单
 *
 * @property int $id
 * @property int $user_id 用户 id
 * @property string $type 支付方式
 * @property string $tradeid 外部订单号
 * @property string $attach 附加数据
 * @property int $fee 订单总额，单位：分
 * @property int $fee_pay 实付金额，单位：分
 * @property int $goal 订单用途
 * @property int $state 订单状态
 * @property int $time_create 创建时间
 * @property int $time_update 更新时间（被动通知）
 *
 * @property-read Identity $identity 通行证信息
 * @property-read string $outTradeNo
 * @property-read string $goalLabel
 *
 * @author William Chan <root@williamchan.me>
 */
class Payment extends ActiveRecord
{
    const GOAL_NONE = 0;
    const GOAL_POINT = 1; // 购买积分
    const GOAL_TRANSFER = 2; // 提现

    const STATE_NONE = 0; // 未付
    const STATE_SUCCESS = 1; // 成功
    const STATE_FAIL = 2; // 失败
    const STATE_REFUND = 3; // 退款

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'payment';
    }

    /**
     * 支持商户订单号
     * @inheritdoc
     * @return static
     */
    public static function findOne($condition)
    {
        if (is_string($condition) && strlen($condition) >= 18) {
            $time = strtotime(substr($condition, 0, 14));
            $condition = substr($condition, 14);
        }
        $model = parent::findOne($condition);
        if ($model !== null && isset($time) && $time != $model->time_create) {
            $model = null;
        }
        return $model;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'fee'], 'required'],
            [['type'], 'in', 'range' => array_keys(static::typeLabels())],
            [['goal'], 'integer'],
            [['attach'], 'string', 'max' => 128],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_create = time();
        }
        $this->time_update = time();
        return parent::beforeSave($insert);
    }

    /**
     * 处理订单支付结果
     * @inheritdoc
     */
    public function afterSave($insert, $changedAttributes)
    {
        // if (!$insert && in_array('state', $changedAttributes) && $this->state == self::STATE_SUCCESS) {
        //     $this->handleSuccess();
        // }
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * 关联 Identity
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id']);
    }

    /**
     * @return string 商户订单号
     */
    public function getOutTradeNo()
    {
        return date('YmdHis', $this->time_create) . sprintf('%04d', $this->id);
    }

    /**
     * @return string 类型名称
     */
    public function getTypeLabel()
    {
        $labels = static::typeLabels();
        return isset($labels[$this->type]) ? $labels[$this->type] : null;
    }

    /**
     * @return string 用途描述
     */
    public function getGoalLabel()
    {
        switch ($this->goal) {
            case self::GOAL_POINT:
                return '购买积分';
            case self::GOAL_TRANSFER:
                return '积分提现';
            default:
                return '无';
        }
    }

    /**
     * 生成数据签名
     * @param array $data
     * @return string
     */
    public function generateSign($data)
    {
        if ($this->type === 'wxpay') {
            $wechat = Yii::$app->get('wechatApp');
            /* @var $wechat \app\components\WechatApp */
            unset($data['sign']);
            ksort($data);
            $raw = '';
            foreach ($data as $key => $value) {
                if ($value !== '' && !is_array($value)) {
                    $raw .= $key . '=' . $value . '&';
                }
            }
            $raw .= 'key=' . $wechat->paySecret;
            return strtoupper(md5($raw));
        } else {
            return null;
        }
    }

    /**
     * 订单支付查询
     * 其中微信订单不支持 APP 产生的订单，只支持公众号。
     * @return array 至少包含 errcode=[SUCCESS|FAIL], errmsg 及其它必要数据
     */
    public function query()
    {
        $result = ['errcode' => 'FAIL', 'errmsg' => 'Unknown'];
        if ($this->type === 'wxpay') {
            $wechat = Yii::$app->get('wechatApp');
            /* @var $wechat \app\components\WechatApp */
            $data = [
                'appid' => $wechat->appId,
                'mch_id' => $wechat->payId,
                'out_trade_no' => $this->outTradeNo,
                'nonce_str' => Yii::$app->security->generateRandomString(16), // 拼算力了 高并发就死了
            ];
            $data['sign'] = $this->generateSign($data);

            // send request
            $url = 'https://api.mch.weixin.qq.com/pay/orderquery';
            $xml = Html::arrayToXml($data);
            $headers['Content-Type'] = 'application/xml';
            $response = Muggle::httpRequest('POST', $url, $xml, $headers);

            // result
            if ($response === false) {
                $result['errmsg'] = 'Request failed';
            }
            $response = Html::xmlToArray($response);
            if (!isset($response['result_code'])) {
                $result['errmsg'] = isset($response['return_msg']) ? $response['return_msg'] : 'Response failed';
            } else {
                $result['errcode'] = $response['result_code'];
                $result['errmsg'] = $response['return_msg'];
                if ($response['result_code'] === 'SUCCESS') {
                    if (!isset($response['sign']) || $response['sign'] !== $this->generateSign($response)) {
                        $result['errcode'] = 'FAIL';
                        $result['errmsg'] = 'Response sign error';
                    } else {
                        $result['trade_state'] = $response['trade_state'];
                        $result['out_trade_no'] = $response['out_trade_no'];
                        if ($result['trade_state'] === 'SUCCESS') {
                            $result['total_fee'] = (int) $response['total_fee'];
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 下单提交给支付方并返回数据
     * @param array $data 初始数据
     * @return array 至少包含 errcode=[SUCCESS|FAIL], errmsg 及其它必要数据
     */
    public function submit($data = [])
    {
        $result = ['errcode' => 'FAIL', 'errmsg' => 'Unknown'];
        if ($this->type === 'wxpay') {
            $wechat = Yii::$app->get('wechatApp');
            /* @var $wechat \app\components\WechatApp */
            // basic data
            $data['nonce_str'] = Yii::$app->security->generateRandomString(16);
            if (!isset($data['spbill_create_ip'])) {
                $data['spbill_create_ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
            }
            // url & data
            $headers = [];
            if ($this->goal == self::GOAL_POINT) {
                // 充值
                if (!isset($data['trade_type'])) {
                    $data['trade_type'] = 'JSAPI';
                } elseif ($data['trade_type'] === 'APP') {
                    $wechat->setScenario('app');
                }
                if ($data['trade_type'] !== 'JSAPI') {
                    $data['openid'] = '';
                }
                $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
                $data['appid'] = $wechat->appId;
                $data['mch_id'] = $wechat->payId;
                $data['body'] = $this->getGoalLabel();
                $data['attach'] = strval($this->attach);
                $data['total_fee'] = intval($this->fee);
                $data['out_trade_no'] = $this->getOutTradeNo();
            } elseif ($this->goal == self::GOAL_TRANSFER) {
                // 提现
                // $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
                // $data['mch_appid'] = $wechat->appId;
                // $data['mchid'] = $wechat->payId;
                // $data['check_name'] = 'NO_CHECK';
                // $data['amount'] = intval($this->fee);
                // $data['desc'] = $this->getGoalLabel();
                // $data['partner_trade_no'] = $this->getOutTradeNo();
                // $headers['ssl'] = [
                //     'verify_peer_name' => false,
                //     'local_cert' => Yii::getAlias('@app/static/wxpay-cert.pem'),
                // ];
            }

            // +notify_url
            $data['sign'] = $this->generateSign($data);
            $xml = Html::arrayToXml($data);
            $headers['Content-Type'] = 'application/xml';
            $response = Muggle::httpRequest('POST', $url, $xml, $headers);

            // result
            if ($response === false) {
                $result['errmsg'] = 'Request failed';
            }
            $response = Html::xmlToArray($response);
            if (!isset($response['result_code'])) {
                $result['errmsg'] = isset($response['return_msg']) ? $response['return_msg'] : 'Response failed';
            } else {
                $result['errcode'] = $response['result_code'];
                $result['errmsg'] = $response['return_msg'];
                if ($response['result_code'] === 'SUCCESS' && $this->goal == self::GOAL_POINT) {
                    if (!isset($response['sign']) || $response['sign'] !== $this->generateSign($response)) {
                        $result['errcode'] = 'FAIL';
                        $result['errmsg'] = 'Response sign error';
                    } else {
                        if ($response['trade_type'] === 'JSAPI') {
                            $return = [
                                'appId' => $response['appid'],
                                'timeStamp' => strval(time()),
                                'nonceStr' => $response['nonce_str'],
                                'package' => 'prepay_id=' . $response['prepay_id'],
                                'signType' => 'MD5',
                            ];
                            $return['paySign'] = $this->generateSign($return);
                        } elseif ($response['trade_type'] === 'APP') {
                            $return = [
                                'appid' => $response['appid'],
                                'partnerid' => $response['mch_id'],
                                'prepayid' => $response['prepay_id'],
                                'package' => 'Sign=WXPay',
                                'noncestr' => $response['nonce_str'],
                                'timestamp' => strval(time()),
                            ];
                            $return['sign'] = $this->generateSign($return);
                        } elseif ($response['trade_type'] === 'NATIVE') {
                            $return = ['code_url' => $response['code_url']];
                            $return['qrcode_url'] = Url::to(['/site/qrcode', 'text' => $response['code_url']], true);
                        } elseif ($response['trade_type'] === 'MWEB') {
                            $return = ['mweb_url' => $response['mweb_url']];
                        } else {
                            $return = $response;
                        }
                        $result += $return;
                    }
                }
                if (isset($response['payment_no'])) {
                    $result['tradeid'] = $response['payment_no'];
                }
            }
        }
        return $result;
    }

    /**
     * 支付成功处理
     */
    protected function handleSuccess()
    {
        // if ($this->goal == self::GOAL_POINT) {
        //     $num = intval($this->fee_pay / 10);
        //     $remark = $this->getGoalLabel() . ' #' . $this->outTradeNo;
        //     PointLog::add($this->user_id, $num, $remark);
        // }
    }

    /**
     * @return array
     */
    public static function typeLabels()
    {
        return [
            'wxpay' => '微信支付',
            'alipay' => '支付宝',
        ];
    }
}
