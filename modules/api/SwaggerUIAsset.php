<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api;

use yii\web\AssetBundle;

/**
 * SwaggerUI 资源包
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class SwaggerUIAsset extends AssetBundle
{
    public $sourcePath = '@bower/swagger-ui/dist';

    public $js = [
        'lib/object-assign-pollyfill.js',
        'lib/jquery-1.8.0.min.js',
        'lib/jquery.slideto.min.js',
        'lib/jquery.wiggle.min.js',
        'lib/jquery.ba-bbq.min.js',
        'lib/handlebars-4.0.5.js',
        'lib/lodash.min.js',
        'lib/backbone-min.js',
        'swagger-ui.js',
        'lib/highlight.9.1.0.pack.js',
        'lib/highlight.9.1.0.pack_extended.js',
        'lib/jsoneditor.min.js',
        'lib/marked.js',
        //'lib/swagger-oauth.js',
        'lang/translator.js',
        'lang/zh-cn.js',
    ];

    public $css = [
        'css/typography.css',
        'css/reset.css',
        'css/screen.css',
    ];
}
