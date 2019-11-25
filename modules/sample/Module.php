<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\sample;

use app\modules\UrlRule;
use Yii;

/**
 * sample web 控制器需要先注册 bootstrap 这只是个例子
 *
 * @author William Chan <root@williamchan.me>
 */
class Module extends \yii\base\Module implements \yii\base\BootstrapInterface
{
    /**
     * 模块启动代码
     * 注册 url 规则集、设定控制器命名空间
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof \yii\web\Application) {
            $app->getUrlManager()->addRules([[
                'class' => UrlRule::class,
                'module' => $this,
            ]], false);
        }
    }
}
