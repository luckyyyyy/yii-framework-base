<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules;

/**
 * 可自定义 URL 规则接口
 *
 * @author William Chan <root@williamchan.me>
 */
interface UrlCustomizable
{
    /**
     * 定义 URL 规则集
     * 生成基于模块 id 下的 URL 美化规则
     *
     * ['GET posts/<id:\d+>' => 'view']
     * ['GET posts/hello' => ['view', 'id' => 1]]
     *
     * @return array
     */
    public static function urlRules();
}
