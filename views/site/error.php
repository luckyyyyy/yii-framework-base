<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $name string */
/* @var $message string */
/* @var $exception Exception */

$this->context->layout = 'error';
$this->title = $name;
?>
<div class="site-error">
    <h2><?= Html::encode($this->title) ?></h2>
    <p class="site-error__alert">
        <?= nl2br(Html::encode($message)) ?>
    </p>
    <address>
        Please <a href="javascript:window.location.reload()">retry</a> or contact
        <em>William Chan</em> if you have any way, thank you.
    </address>
    <?php if (YII_DEBUG && isset($exception)): ?>
        <pre>## <?= $exception->getFile() ?>(<?= $exception->getLine() ?>)<?= PHP_EOL ?><?= $exception->getTraceAsString() ?></pre>
    <?php endif; ?>
</div>
