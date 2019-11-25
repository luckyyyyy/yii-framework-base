<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

/**
 * @SWG\Swagger(
 *     schemes={"https", "http"},
 *     basePath="/api",
 *     @SWG\Info(
 *         version="1.0.0",
 *         title="muggle — RESTful API",
 *         description="Version: __1.0.0__",
 *         @SWG\Contact(name="William Chan", email="root@williamchan.me")
 *     )
 * )
 *
 * @SWG\Tag(name="Others", description="其它")
 *
 * @SWG\Definition(
 *     definition="Error",
 *     required={"errcode", "errmsg"},
 *     @SWG\Property(property="errcode", type="integer", description="出错代码, 0=成功", example=400),
 *     @SWG\Property(property="errmsg", type="string", description="出错说明, OK=成功", example="Bad Request."),
 *     @SWG\Property(property="data", type="mixed", description="出错或结果数据")
 * )
 *
 * @SWG\Parameter(parameter="offsetPageParam", in="query", name="page", type="string", description="分页的页码"),
 * @SWG\Parameter(parameter="offsetPointParam", in="query", name="point", type="string", description="瀑布流的最后ID")
 * @SWG\Parameter(parameter="offsetLimitParam", in="query", name="limit", type="string", description="每次最大请求的数量"),
 *
 * @SWG\Definition(
 *     definition="Flags",
 *     type="object",
 *     @SWG\Property(
 *         property="flag (n^2)",
 *         type="object",
 *         ref="#/definitions/FlagsItem"
 *     ),
 * )
 *
 * @SWG\Definition(
 *     definition="FlagsItem",
 *     type="object",
 *     @SWG\Property(property="label", type="string",  description="权限名称（管理员可见或其他强制可见情况）", example="标记label"),
 *     @SWG\Property(property="have", type="boolean", description="是否拥有该标签（管理员可见所有）", example=true)
 * )
 *
 * @SWG\Definition(
 *     definition="ImageInfoFormat",
 *     description="很多看不懂的参数建议参考 EXIF2.31 http://oss-attachment.cn-hangzhou.oss.aliyun-inc.com/DC-008-Translation-2016-E.pdf",
 *     type="object",
 *     @SWG\Property(property="url", type="string", description="图片真实路径", example="/path/to"),
 *     @SWG\Property(property="rgb", type="boolean", description="图片主色调", example="ff0000"),
 *     @SWG\Property(property="isGif", type="boolean", description="是否是GIF", example=true),
 *     @SWG\Property(property="imageHeight", type="string", description="图片高度", example="2338"),
 *     @SWG\Property(property="imageWidth", type="string", description="图片宽度", example="1653"),
 *     @SWG\Property(property="fileSize", type="string", description="图片大小 字节", example="1555102"),
 *     @SWG\Property(property="format", type="string", description="图片格式", example="jpg"),
 *     @SWG\Property(property="exifTag", type="string", description="exifTag", example="192"),
 *     @SWG\Property(property="hostComputer", type="string", description="hostComputer", example="1653"),
 *     @SWG\Property(property="make", type="string", description="make", example="Brother"),
 *     @SWG\Property(property="model", type="string", description="model", example="DCP-1618W"),
 *     @SWG\Property(property="orientation", type="string", description="orientation", example="1"),
 *     @SWG\Property(property="pixelXDimension", type="string", description="pixelXDimension", example="1653"),
 *     @SWG\Property(property="pixelYDimension", type="string", description="pixelYDimension", example="2338"),
 *     @SWG\Property(property="resolutionUnit", type="string", description="resolutionUnit", example="2"),
 *     @SWG\Property(property="software", type="string", description="software", example="Apple Image Capture"),
 *     @SWG\Property(property="xResolution", type="string", description="xResolution", example="200/1"),
 *     @SWG\Property(property="yResolution", type="string", description="yResolution", example="200/1"),

 * )
 */
use app\components\Html;
use app\modules\api\SwaggerUIAsset;
use yii\helpers\Url;

SwaggerUIAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>API 文档 - Powered by SwaggerUI v2</title>
    <?php $this->head() ?>
</head>
<body class="swagger-section">
<?php $this->beginBody() ?>
<div id="header">
    <div class="swagger-ui-wrap">
        <a id="logo" href="/">muggle API</a>
        <form id="api_selector">
            <div class="input">
                <input placeholder="http://example.com/api" id="input_baseUrl" name="baseUrl" type="text"></div>
            <div class="input"><input placeholder="Bearer access_token" id="input_apiKey" type="text"></div>
            <div class="input"><a id="explore" href="#" data-sw-translate>Explore</a></div>
        </form>
    </div>
</div>
<div id="message-bar" class="swagger-ui-wrap" data-sw-translate>&nbsp;</div>
<div class="swagger-ui-wrap">
    <div class="yii-user">
        <p>当前登录的用户：
            <?php if (Yii::$app->user->isGuest): ?>
            未登录
            <?php else: ?>
            <img width="30" src="<?=Html::extUrl(Yii::$app->user->identity->avatar)?>" alt="">
            <?=Yii::$app->user->identity->name?>
            <?php endif; ?>
        <p>当前用户FromReferral：<?=Yii::$app->user->referral?></p>
        <p>当前用户身份：
            <?php if (Yii::$app->user->isGuest): ?>
            游客
            <?php elseif (Yii::$app->user->isRoot): ?>
            Root
            <?php elseif (Yii::$app->user->isSuper): ?>
            超级管理员
            <?php elseif (Yii::$app->user->isAdmin('%')): ?>
            管理员 <?=Yii::$app->user->identity->admin->summary?>
            <?php else: ?>
            普通用户
            <?php endif; ?>
        </p>
        <p>身份切换：
            <a href="<?= Url::to(['/api/user/debug/1', 'redirect_uri' => Url::to()]) ?>">用户#1</a> |
            <a href="<?= Url::to(['/api/user/debug/2', 'redirect_uri' => Url::to()]) ?>">用户#2</a> |
            <a href="<?= Url::to(['/api/user/debug/3', 'redirect_uri' => Url::to()]) ?>">用户#3</a> |
            <a href="<?= Url::to(['/api/user/debug/4', 'redirect_uri' => Url::to()]) ?>">用户#4</a> |
            <a href="<?= Url::to(['/api/user/logout', 'redirect_uri' => Url::to()]) ?>">退出登录</a>
        </p>
    </div>
    <div id="swagger-ui-container"></div>
</div>
<style>g
    body, html {
        background: #1e1e1e;
        color: #dadada
    }
    /* 解决超长问题 */
    .operation-params .snippet {
        max-width: 230px;
        max-height: 220px;
    }
    .operation-params .model-signature pre code {
        min-height: 188px;
    }
    .yii-user {
        background: #1e1e1e;
        border: 1px solid #61affe;
        box-shadow: 1px 1px 5px #61affe;
        padding: 10px;
        margin: 10px 0;
    }
    .yii-user p {
        padding: 0 !important;
        margin: 3px 0 !important;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource.active div.heading h2 a, .swagger-section .swagger-ui-wrap ul#resources li.resource:hover div.heading h2 a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource div.heading ul.options li a:hover
    {
        color: #dadada;
    }
    .swagger-section .swagger-ui-wrap a {
        color: #88ce00;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation div.heading h3 span.path a {
        color: #dadada;
    }
    .swagger-section .swagger-ui-wrap .model-signature .signature-nav a:hover,
    .swagger-section .swagger-ui-wrap .model-signature .signature-nav .selected {
        color: #dadada;
    }
    .swagger-section .swagger-ui-wrap p {
        color: #dadada;
    }
    /* 难看死了 给你改改 */
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.heading {
        background-color: rgba(97,175,254,.4);
        border: 1px solid #61affe;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.heading h3 span.http_method a {
        background-color: #61affe;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.heading ul.options li a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.content h4 {
        color: #61affe;
        text-shadow: 1px 1px 1px #333;
    }

    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.heading {
        background-color: rgba(73,204,144,.4);
        border: 1px solid #49cc90;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.heading h3 span.http_method a {
        background-color: #49cc90;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.heading ul.options li a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.content h4 {
        color: #49cc90;
        text-shadow: 1px 1px 1px #333;
    }

    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.heading {
        background-color: rgba(249,62,62,.4);
        border: 1px solid #f93e3e;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.heading h3 span.http_method a {
        background-color: #f93e3e;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.heading ul.options li a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.content h4 {
        color: #f93e3e;
        text-shadow: 1px 1px 1px #333;
    }

    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.heading {
        background-color: rgba(252,161,48,.4);
        border: 1px solid #fca130;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.heading h3 span.http_method a {
        background-color: #fca130;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.heading ul.options li a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.content h4 {
        color: #fca130;
        text-shadow: 1px 1px 1px #333;
    }

    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.heading {
        background-color: rgba(80,227,194,.4);
        border: 1px solid #50e3c2;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.heading h3 span.http_method a {
        background-color: #50e3c2;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.heading ul.options li a,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.content h4 {
        color: #50e3c2;
        text-shadow: 1px 1px 1px #333;
    }

    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.delete div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.post div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.head div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.patch div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.put div.content,
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation.get div.content {
        border-top: none;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation div.content div.sandbox_header input.submit {
        background-color: #1e1e1e;
        color: #dadada !important;
        width: 80px;
        outline: 0;
        cursor: pointer;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation div.content div.sandbox_header input.submit:hover {
        background-color: #3e3e3e;
    }

    .block.response_headers {
        line-height: 1.2 !important;
    }
    .swagger-section .swagger-ui-wrap .model-signature .propType {
        color: #9e9efd !important;
    }
    .swagger-section .swagger-ui-wrap .model-signature .description .strong {
        color: #fafafa !important;
    }
    .swagger-section .hljs {
        background: rgba(0,0,0,0);
    }
    .swagger-section .swagger-ui-wrap table thead tr th {
        color: #dadada !important;
    }
    .swagger-section .swagger-ui-wrap textarea,
    .swagger-section .swagger-ui-wrap input.parameter {
        background-color: #1e1e1e;
        color: #dadada !important;
    }
    .swagger-section .swagger-ui-wrap pre {
        background-color: #1e1e1e;
        border: 1px solid #8ccaff;
        color: #dadada !important;
    }
    .swagger-section .hljs, .swagger-section pre code {
        color: #eaeaea !important;
    }
    span.hljs-number {
        color: #b5cea8 !important;
    }
    span.hljs-literal {
        color: #569cd6 !important;
    }
    span.hljs-string {
        color: #ce9178 !important;
    }
    span.hljs-attr {
        color: #9cd9fe !important;
    }
    .swagger-section .swagger-ui-wrap .model-signature pre:hover  {
        background-color: #1f1f1f;
    }
    .swagger-section .swagger-ui-wrap ul#resources li.resource ul.endpoints li.endpoint ul.operations li.operation div.content div.response div.block pre {
        max-height: none;
    }
</style>
<script pos="ready">
    var url = window.location.search.match(/url=([^&]+)/);
    if (url && url.length > 1) {
        url = decodeURIComponent(url[1]);
    } else {
        url = '<?= Url::to(['json']) ?>';
    }
    // Pre load translate...
    if (window.SwaggerTranslator) {
        window.SwaggerTranslator.translate();
    }
    window.swaggerUi = new SwaggerUi({
        url: url,
        dom_id: 'swagger-ui-container',
        supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
        onComplete: function () {
            if (window.SwaggerTranslator) {
                window.SwaggerTranslator.translate();
            }
            $('pre code').each(function (i, e) {
                hljs.highlightBlock(e)
            });
            addApiKeyAuthorization();
        },
        onFailure: function () {
            log('Unable to Load SwaggerUI');
        },
        docExpansion: 'none',
        apisSorter: 'alpha',
        defaultModelRendering: 'schema',
        showRequestHeaders: false
    });
    function addApiKeyAuthorization() {
        var key = $('#input_apiKey').val();
        if (key && key.trim() != '') {
            var apiKeyAuth = new SwaggerClient.ApiKeyAuthorization('Authorization', 'Bearer ' + key, 'header');
            window.swaggerUi.api.clientAuthorizations.add('api_key', apiKeyAuth);
        }
    }
    $('#input_apiKey').change(addApiKeyAuthorization);
    window.swaggerUi.load();
    function log() {
        if ('console' in window) {
            console.log.apply(console, arguments);
        }
    }
</script>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
