<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api;

use Yii;
use yii\base\BaseObject;
use yii\helpers\Json;

/**
 * 带元数据的响应结果
 * @author William Chan <root@williamchan.me>
 */
class MetaResponse extends BaseObject
{
    /**
     * @var mixed 真实数据
     */
    public $data;

    /**
     * @var array|bool 元数据，追加到顶层；如果传入 true 则不包含 errcode 层级
     */
    public $meta;

    /**
     * 构造函数
     * @param mixed $data original response data
     * @param mixed $meta meta data
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($data = null, $meta = true, $config = [])
    {
        $this->data = $data;
        $this->meta = $meta;
        parent::__construct($config);
    }

    /**
     * String magic method.
     * @return string the JSON result
     */
    public function __toString()
    {
        return Json::encode($this->formatData());
    }

    /**
     * @return mixed 格式化结果数据
     */
    public function formatData()
    {
        if ($this->meta === true) {
            return $this->data;
        } else {
            $data = ['errcode' => 0, 'errmsg' => 'OK', 'data' => $this->data];
            if (is_array($this->meta)) {
                $data = array_merge($data, $this->meta);
            }
            return $data;
        }
    }
}
