<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
define('YII_DEBUG', true);
define('YII_ENV', 'dev');

$config = require(__DIR__ . '/../config/_bootstrap.php');
$config['components']['urlManager']['showScriptName'] = true;
$config['modules']['debug']['allowedIPs'] = ['127.0.0.1', '*'];

if (!defined('MUGGLE_DEBUG_ENABLE') || !MUGGLE_DEBUG_ENABLE) {
    header('HTTP/1.1 400 Bad Request');
    exit(0);
}

(new \yii\web\Application($config))->run();
