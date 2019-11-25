<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\BaseObject;

/**
 * 红点角标信息（抽象）
 * @author William Chan <root@williamchan.me>
 */
abstract class BaseBadge extends BaseObject
{
    protected $_cacheCount;
    protected $_cacheHas;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->reset();
    }

    /**
     * 重置
     */
    public function reset()
    {
        $this->_cacheCount = $this->_cacheHas = [];
    }

    /**
     * 红点角标概要
     * @param string $type 类型：all/message
     * @return array
     */
    abstract public function summary($type);

    /**
     * 获取角标数量
     * @param string $selector 选择器
     * @return int
     */
    abstract public function count($selector);

    /**
     * 获取红点标记
     * @param string $selector 选择器
     * @param bool $narrow 狭义概念
     * @return bool
     */
    abstract public function has($selector, $narrow = false);

    /**
     * 获取狭义红点标记(数据库实时)
     * @param string $selector
     * @return bool
     */
    abstract public function hasNarrow($selector);

    /**
     * @param string $selector
     * @return \yii\db\ActiveQuery
     */
    abstract protected function getQuery($selector);
}
