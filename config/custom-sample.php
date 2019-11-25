<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

define('MUGGLE_DEBUG_ENABLE', true);
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

return [
    'components' => [
        'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => 'redis',
        ],
        'cache2' => [
            'class' => 'yii\redis\Cache',
            'redis' => 'redis2',
        ],
        'db' => [
            'dsn' => 'mysql:host=localhost;dbname=muggle',
            'username' => 'root',
            'password' => '123456',
            'tablePrefix' => 'wechat_', // 前缀没有任何意义只是为了方便切换公众号场景
            // 'slaveConfig' => [
            //     'username' => 'root',
            //     'password' => '',
            // ],
            'slaves' => [
                //['dsn' => 'mysql:host=10.10.10.6;dbname=muggle'],
            ],
        ],
        'storage' => [
            'class' => 'app\components\storage\Aliyun',
            'bucket' => 'jx3-jh',
            'bucketPoint' => 'cn-hangzhou',
            'bucketHost' => '',
            'internal' => false,
            'accessKeyId' => '',
            'accessKeySecret' => '',
        ],
        'sms' => [
            'class' => 'app\components\sms\Aliyun',
            'accessKeyId' => '',
            'accessKeySecret' => '',
        ],
        'redis' => [
            'hostname' => '127.0.0.1',
            'database' => 1,
            // 'socketClientFlags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        ],
        'redis2' => [
            'hostname' => '127.0.0.1',
            'database' => 2,
            // 'socketClientFlags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
        ],
        // @see http://php.net/manual/en/function.session-set-cookie-params.php
        'session' => [
            'redis' => 'redis2',
            'timeout' => 86400,
            'cookieParams' => [
                'lifetime' => 0,
                'httpOnly' => true,
            ],
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'wechatApp' => [
            'scenarios' => [
                'zhongce' => [
                    'prefix' => 'name',
                    'appId' => '1',
                    'appSecret' => '2',
                    'token' => '3',
                    'encodingAesKey' => '4',
                    'type' => 'mp',
                    'templates' => [
                        // 众测剩余积分提醒 《助力成功通知》
                        // 正式：7PsPTAgEdWLAaJtQcCOARSKOoVBvY3qWxxZH1Wckx8A
                        // 测试：pWmaIls_7GfI_h6omBqHmyyLSe1_vwyeuLiTxOW4RgQ
                        'LEFT_POINT_NOTIFY' => 'pWmaIls_7GfI_h6omBqHmyyLSe1_vwyeuLiTxOW4RgQ',
                        // 助力成功通知 《助力成功通知》
                        // 正式：bnUUtJCr9dkpcqkRCrj_gK6RIJev8zWiu22BHd2gG8k
                        // 测试：xIu8QRLM-ybpUef5V68dL5SL5QXx-nUIL6luAl59CS8
                        'POINT_ADD' => 'xIu8QRLM-ybpUef5V68dL5SL5QXx-nUIL6luAl59CS8',
                        // 众测预热/进行时通知 《众测派发成功通知》
                        // 正式：efcm2GmbaMrlNWFkLEvh2bsAhEOzTp5HDcbKMr78ars
                        // 测试：PemPIvpK4e4Mdh_vsk6HlXkZu4Y0V3Kid1NOp4fGyVk
                        'READY_NOTIFY' => 'PemPIvpK4e4Mdh_vsk6HlXkZu4Y0V3Kid1NOp4fGyVk',
                        // 中奖通知 《抽奖结果通知》
                        // 正式：TB1thOszknd-X1XUwwTTi9ByuPb5VGXOXJE2l9TYaB0
                        // 测试：暂无
                        'lOTTERY_NOTIFY' => 'PemPIvpK4e4Mdh_vsk6HlXkZu4Y0V3Kid1NOp4fGyVk',
                        // 发货提醒 《发货提醒》
                        // 正式：eMpYStqsH_wuvmTz0bsPtmD1Up0_hXZtIUUU6tQYeJg
                        // 测试：暂无
                        'SHIPPING_NOTICE' => 'eMpYStqsH_wuvmTz0bsPtmD1Up0_hXZtIUUU6tQYeJg',
                    ],
                ]
            ],
        ],
    ],
    // 'catchAll' => ['site/offline'],
    'params' => [
        'host.testing' => 'http://localhost', // 众测地址
        'xs.server.index' => '127.0.0.1:8383',
        'xs.server.search' => '127.0.0.1:8384',
    ],
];
