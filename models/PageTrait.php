<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;

/**
 * 简易分页逻辑处理公共代码
 *
 * @author William Cham <root@williamchan.me>
 */
trait PageTrait
{
    /**
     * @inheritdoc
     */
    public static function find()
    {
        return Yii::createObject(PageQuery::class, [get_called_class()]);
    }
}
