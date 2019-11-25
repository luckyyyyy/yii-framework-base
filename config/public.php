<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

return [
    'basePath' => dirname(__DIR__),
    'components' => [
        // 存放无关紧要的数据
        'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => 'redis',
        ],
        // 存放重要数据【禁止手动清空】
        'cache2' => [
            'class' => 'yii\redis\Cache',
            'redis' => 'redis2',
        ],
        // 使用 \app\components\caching\ShmCache 扩展时，存放在本地（机）【速度最快 但是不支持分布式】
        'cache3' => [
            'class' => 'yii\caching\FileCache',
        ],
        'mutex' => [
            'class' => 'yii\redis\Mutex',
            'redis' => 'redis',
            'keyPrefix' => 'MUTEX_',
            'autoRelease' => true,
        ],
        'mutex2' => [
            'class' => 'yii\redis\Mutex',
            'redis' => 'redis2',
            'keyPrefix' => 'MUTEX2_',
            'autoRelease' => true,
        ],
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=muggle',
            'tablePrefix' => '',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'enableSchemaCache' => !YII_DEBUG,
            'schemaCache' => 'cache3',
            'attributes' => [
                PDO::ATTR_PERSISTENT => true,
            ],
            'slaveConfig' => [
                'username' => 'root',
                'password' => '',
                'attributes' => [
                    PDO::ATTR_TIMEOUT => 1,
                    PDO::ATTR_PERSISTENT => true,
                ],
            ],
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                'file' => [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                    'except' => ['yii\web\*'],
                ],
            ],
        ],
        'image' => [
            'class' => 'app\components\Image',
        ],
        'baiduAi' => [
            'class' => 'app\components\BaiduAi',
        ],
        'juhe' => [
            'class' => 'app\components\Juhe',
            'appKey' => '',
        ],
        'storage' => [
            'class' => 'app\components\storage\Aliyun',
            'bucket' => '',
            'bucketPoint' => 'cn-hangzhou',
            'bucketHost' => '',
            'accessKeyId' => '',
            'accessKeySecret' => '',
        ],
        'cloud' => [
            'class' => 'app\components\cloud\Aliyun',
            'accessKeyId' => '',
            'accessKeySecret' => '',
        ],
        // 腾讯云 只开通的文智接口 不要滥用
        'qcloud' => [
            'class' => 'app\components\cloud\Qcloud',
            'apiSecretId' => '',
            'apiSecretKey' => '',
        ],
        'sms' => [
            'class' => 'app\components\sms\Aliyun',
            'accessKeyId' => '',
            'accessKeySecret' => '',
            'scenarios' => [
                'COMMON_VERIFY' => ['internal' => 'SMS_143862646', 'outside' => 'SMS_143862647'],
                'CONSOLE_PASS' => ['internal' => 'SMS_153885719', 'outside' => ''],
                'CONSOLE_DENY' => ['internal' => 'SMS_153885721', 'outside' => ''],
                'CONSOLE_WITHDRAW_FAIL' => ['internal' => 'SMS_153985048', 'outside' => ''],
                'CONSOLE_WITHDRAW_SUCCESS' => ['internal' => 'SMS_153980069', 'outside' => ''],
            ],
        ],
        'queue' => [
            'class' => 'app\components\QueueJob',
            'redis' => 'redis2',
        ],
        'queue2' => [
            'class' => 'app\components\QueueJob',
            'redis' => 'redis2',
            'name' => 'queue2',
        ],
        'queue3' => [
            'class' => 'app\components\QueueJob',
            'redis' => 'redis2',
            'name' => 'queue3',
        ],
        /**
         * Warning
         * 这里有个坑注意 使用复用链接的时候
         * php connect 是直接在 fpm 里记录了 socket 描述字复用 根据 ip + port
         * 所以如果两个 reids 请使用不同的ip 否则不要加 STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT 不然导致混乱
         */
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => 'redis',
            'database' => 0,
            // 'socketClientFlags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        ],
        'redis2' => [
            'class' => 'yii\redis\Connection',
            'hostname' => 'redis',
            'database' => 2,
            //'socketClientFlags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        ],
        'session' => [
            'class' => 'yii\redis\Session',
        ],
        'wechatApp' => [
            'class' => 'app\components\WechatApp',
        ],
        'wechatMp' => [
            'class' => 'app\components\WechatMp',
        ],
        'xunsearch' => [
            'class' => 'hightman\xunsearch\Connection',
            'iniDirectory' => '@app/config',
        ],
    ],
    'modules' => [],
    'params' => require(__DIR__ . '/params.php'),
];
