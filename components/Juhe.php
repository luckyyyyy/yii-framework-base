<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\Exception;
use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * 聚合数据
 * url: http://v.juhe.cn/exp/index
 * appkey: 27865a233720b7fc
 *
 * @see http://v.juhe.cn/exp/index
 * @property-read string $appKey
 *
 * @author William Chan <root@williamchan.me>
 */
class Juhe extends BaseObject
{
    use LogTrait;

    /**
     * @var string APP Key
     */
    public $appKey = '';

    /**
     * @var string url
     */
    public $apiBaseUrl = 'https://v.juhe.cn/';

    /**
     * @var array 快递公司
     */
    const EXPRESS_COMPANY = [
        'sf' => '顺丰快递',
        'sto' => '申通快递',
        'yt' => '圆通快递',
        'zto' => '中通快递',
        'yd' => '韵达快递',
        'ems' => 'EMS邮政包裹',
        'ht' => '汇通快递',
        'gt' => '国通快递',
        'jd' => '京东',
        'zjs' => '宅急送',
    ];

    /**
     * 调用快递接口
     * @see http://v.juhe.cn/exp/index
     * @param string $code 快递单号
     * @param string $company 快递公司
     * @param string $method 请求方法 默认POST
     * @param string $dtype 返回格式 默认json
     * @return array 请求接口
     */
    public function getExpressInfo($code, $company, $method = 'POST', $dtype = 'json')
    {
        $params = [
            'com' => $company,
            'no' => $code,
            'dtype' => $dtype,
        ];
        $data = $this->api('exp/index', $params, $method);
        $data = $this->expressFormat($data);
        return $data;
    }

    /**
     * 接口调用
     * @param string $api 接口名称
     * @param array $params 请求参数
     * @param string $method 请求方法
     * @return array 请求接口
     */
    public function api($api, array $params = [], $method = 'POST')
    {
        $url = $this->apiBaseUrl . $api;
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'key=' . urlencode($this->appKey);
        return $this->apiRequest($url, $params, $method);
    }

    /**
     * 接口调用
     * @param string $url 接口完整地址
     * @param string $method 接口请求方式，默认为 GET，还可支持 POST & POST_JSON
     * @param array $params 请求参数
     * @return array 请求结果
     * @throws \yii\base\Exception
     * @throws \Exception
     */
    public function apiRequest($url, array $params = [], $method = 'POST')
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                $response = Muggle::guzzleHttpRequest($method, $url, $params);
                break;
            } catch (\Exception $e) {
                $this->log($method . ' ' . $url . " - try count $i - " . $e->getMessage());
                if ($i === 2) {
                    throw new \yii\web\HttpException(502, '3rd-party error');
                }
            }
        }
        $raw = $response->getBody()->getContents();
        $data = Json::decode($raw);
        if (isset($data['error_code']) && $data['error_code'] === 0) { // 请求成功
            return $data['result'];
        } else {
            $errors = 'Juhe API Error';
            if (isset($data['error_code']) && isset($data['reason'])) {
                $errors = 'Juhe API Error: #' . $data['error_code'] . ' - ' . $data['reason'];
                $this->log($errors . "\n" . $method . ' ' . $url . "\n" . var_export($params, true));
            }
            throw new Exception($errors);
        }
    }

    /**
     * 格式化物流信息
     * @param $data 物流数据
     * @return array
     */
    private function expressFormat($data)
    {
        $list = [];
        foreach ($data['list'] as $k => $item) {
            $list[$k]['time'] = $item['datetime'];
            $list[$k]['status'] = $item['remark'];
        }
        rsort($list); // 按照最新时间倒序
        return [
            'company' => self::EXPRESS_COMPANY[$data['com']] ?? $data['com'],
            'list' => $list,
        ];
    }
}
