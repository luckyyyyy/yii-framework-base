<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

// In the console environment, some path aliases may not exist. Please define these:
Yii::setAlias('@webroot', __DIR__ . '/../web');
Yii::setAlias('@web', '/');

return [
    // Adjust command/callback for JavaScript files compressing:
    'jsCompressor' => 'java -jar runtime/jar/compiler.jar --js {from} --js_output_file {to}',
    //'jsCompressor' => 'java -jar runtime/jar/yuicompressor-2.4.8.jar --nomunge --type js {from} -o {to}',
    // Adjust command/callback for CSS files compressing:
    'cssCompressor' => 'java -jar runtime/jar/yuicompressor-2.4.8.jar --type css {from} -o {to}',
    // The list of asset bundles to compress:
    'bundles' => [
        // 'app\assets\WeuiLayoutAsset',
        // 'app\assets\BsLayoutAsset',
        // 'app\assets\SimditorAsset',
        // 'app\assets\AtwhoAsset',
    ],
    // Asset bundle for compression output:
    'targets' => [
        'all' => [
            'class' => 'yii\web\AssetBundle',
            'basePath' => '@webroot/assets',
            'baseUrl' => '@web/assets',
            'js' => 'all-{hash}.js',
            'css' => 'all-{hash}.css',
            'depends' => [
                'app\assets\FastclickAsset',
                'yii\web\JqueryAsset',
            ],
        ],
        'bs' => [
            'class' => 'yii\web\AssetBundle',
            'basePath' => '@webroot/assets',
            'baseUrl' => '@web/assets',
            'js' => 'bs-{hash}.js',
            'css' => 'bs-{hash}.css',
            'depends' => [
                'yii\bootstrap\BootstrapAsset',
                'yii\bootstrap\BootstrapPluginAsset',
                'app\assets\BsLayoutAsset',
            ],
        ],
    ],
    // Asset manager configuration:
    'assetManager' => [
        'basePath' => '@webroot/assets',
        'baseUrl' => '@web/assets',
        'linkAssets' => true,
    ],
];
