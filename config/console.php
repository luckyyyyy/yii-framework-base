<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

$config = [
    'id' => 'MUGGLE-Console',
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'viewPath' => '@app/commands/views',
];

// just for console
$config['components']['db']['enableSchemaCache'] = false;
$config['components']['redis']['dataTimeout'] = 3600;
// $config['components']['log']['targets']['file']['categories'] = ['yii\db\*'];
// 控制台 User 用于部分需要强依赖用户身份的代码逻辑，默认是一个游客身份的用户。
$config['components']['user'] = [
    'class' => 'app\components\ConsoleUser',
];

// partial core commands
$config['enableCoreCommands'] = false;
$config['controllerMap'] = [
    'asset' => 'yii\console\controllers\AssetController',
    'cache' => 'yii\console\controllers\CacheController',
    //'fixture' => 'yii\console\controllers\FixtureController',
    'help' => 'yii\console\controllers\HelpController',
    //'message' => 'yii\console\controllers\MessageController',
    'migrate' => 'yii\console\controllers\MigrateController',
    //'serve' => 'yii\console\controllers\ServeController',
];
// modules for console
$config['modules'] = [];
// just for dev

// if (YII_ENV_DEV) {
    // $config['bootstrap'][] = 'gii';
    // $config['modules']['gii'] = [
    //     'class' => 'yii\gii\Module',
    // ];
// }

return $config;
