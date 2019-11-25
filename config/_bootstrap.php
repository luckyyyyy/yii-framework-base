<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

$customFile = __DIR__ . '/custom.php';
$custom = file_exists($customFile) ? require($customFile) : [];

// 引入框架
defined('YII_APP_BASE_PATH') or define('YII_APP_BASE_PATH', dirname(__DIR__));
require(YII_APP_BASE_PATH . '/vendor/autoload.php');
require(YII_APP_BASE_PATH . '/vendor/yiisoft/yii2/Yii.php');

// 定义必要 alias

// 生成并返回配置
if (!isset($configFile)) {
    $configFile = __DIR__ . '/web.php';
}

$config = \yii\helpers\ArrayHelper::merge(require(__DIR__ . '/public.php'), require($configFile));
foreach (['components', 'modules'] as $key) {
    if (isset($custom[$key])) {
        foreach ($custom[$key] as $name => $_) {
            if (!isset($config[$key][$name])) {
                unset($custom[$key][$name]);
            }
        }
    }
    // 兼容测试服分支共享缓存问题
    if ($key === 'components' && isset($custom[$key])) {
        foreach ($custom[$key] as $name => $_) {
            if ($name === 'cache' && isset($config[$key][$name])) {
                unset($config[$key][$name]);
                break;
            }
        }
    }
}
$config = \yii\helpers\ArrayHelper::merge($config, $custom);

// 禁用 slaves: GET/HEAD/OPTIONS
if (isset($_SERVER['REQUEST_METHOD']) && !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'])) {
    $config['components']['db']['enableSlaves'] = false;
}
return $config;
