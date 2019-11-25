<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;
use yii\base\ActionFilter;

/**
 * 清除红点角标信息
 * @author William Chan <root@williamchan.me>
 */
class BaseBadgeCleaner extends ActionFilter
{
    /**
     * @var array
     */
    public $setting = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->only = array_keys($this->setting);
    }
}
