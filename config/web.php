<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

$config = [
    'id' => 'muggle',
    'name' => 'muggle - main',
    'language' => 'en-US',
    'bootstrap' => ['api', 'log'],
    'aliases' => [
        '@static' => 'https://cdn.com',
        '@web/uploads' => '@static/uploads',
        '@webroot/uploads' => '@app/static/uploads',
        '@webroot/caches' => '@app/static/zips'
    ],
    'components' => [
        'assetManager' => [
            'linkAssets' => true,
            'appendTimestamp' => true,
            'baseUrl' => '@web/assets',
            // 'bundles' => YII_ENV_PROD ? require(__DIR__ . '/assets-prod.php') : [],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'request' => [
            'cookieValidationKey' => 'qwei9,fpl*&_123;U0FPleES',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'urlManager' => [
            'cache' => 'cache3',
            'enablePrettyUrl' => true,
            'showScriptName' => true,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'site/index',
                'offline' => 'site/offline',
                'login' => 'site/login',
                'shorturl/<id:\w+>' => 'site/short-url',
                'wechat/check/<scenario:\w+>' => 'wechat/check',
                'wechat/image/<scenario:\w+>' => 'wechat/image',
                'wechat/appmsg/<scenario:\w+>' => 'wechat/appmsg',
                'wechat/<scenario:\w+>' => 'wechat/index',
            ],
        ],
        'user' => [
            'class' => 'app\components\User',
            'identityClass' => 'app\models\Identity',
            'enableAutoLogin' => false,
            'loginUrl' => ['login'],
        ],
        'view' => [
            'class' => 'app\components\View',
        ],
    ],
    'modules' => [
        'm' => 'app\modules\mobile\Module',
        'api' => [
            'class' => 'app\modules\api\Module',
            'modules' => [
                'admin' => [
                    'class' => 'app\modules\admin\api\Module',
                    'modules' => [
                        'wechat' => 'app\modules\admin\wechat\api\Module',
                    ],
                ],
                'user' => 'app\modules\user\api\Module',
            ],
        ],
    ],
];

// just for dev
if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];
    // $config['bootstrap'][] = 'gii';
    // $config['modules']['gii'] = [
    //     'class' => 'yii\gii\Module',
    // ];
}
return $config;
