<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use app\games\GameController;
use Yii;

/**
 * View 扩展
 * 增加一些常用扩展操作，如设置 SEO 信息、获取资源、JS/CSS 便捷提取
 *
 * 在 view 中通过以下方式在任意位置嵌入 js 代码，其中 pos 属性不可省略必须放在最后：
 * <script pos="head|begin|end|load|ready">
 * // your custom code
 * </script>
 *
 * 通过以下方式嵌入 css 代码，其中 media 属性可以省略，但必须放在最后：
 * <style media="all">
 * // your css code
 * </style>
 *
 * @author William Chan <root@williamchan.me>
 */
class View extends \yii\web\View
{
    /**
     * @var bool 是否启用微信 Jssdk
     */
    public $enableJssdk = true;

    /**
     * @var array
     */
    protected $cssSnippets = [];

    /**
     * 自动发布 `@webroot/uploads` 至 assets
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (Yii::getAlias('@static') === '') {
            $this->initAliases();
        }
    }

    /**
     * 支持多游戏
     * @inheritdoc
     */
    public function renderFile($viewFile, $params = [], $context = null)
    {
        // if (Yii::$app->controller instanceof GameController) {
        //     $viewFile = Yii::$app->controller->normalizeViewFile($viewFile);
        // }
        return parent::renderFile($viewFile, $params, $context);
    }

    /**
     * 统一规整处理 view 内嵌的 css/js 代码
     * @inheritdoc
     */
    public function endPage($ajaxMode = false)
    {
        $output = ob_get_contents();
        if (strpos($output, self::PH_HEAD) !== false) {
            $output = preg_replace_callback('/<(script|style).*?(?:(media|pos)="(.+?)")?>(.*?)<\/\1>/s', [$this, 'extractSnippet'], $output);
            foreach ($this->cssSnippets as $media => $codes) {
                $this->registerCss(implode("\n", $codes), ['media' => $media], get_class($this->context) . '#css#' . $media);
            }
            ob_clean();
            echo $output;
        }
        parent::endPage($ajaxMode);
    }

    /**
     * @inheritdoc
     */
    protected function renderBodyEndHtml($ajaxMode)
    {
        $result = parent::renderBodyEndHtml($ajaxMode);
        return str_replace('jQuery(', '$(', $result);
    }

    /**
     * Extract script/css snippet as replace callback
     * @param array $match
     * @return string
     */
    protected function extractSnippet($match)
    {
        static $count = 1;
        if ($match[1] === 'script' && $match[3] !== '') {
            $pos = constant('self::POS_' . strtoupper($match[3]));
            if ($pos !== null) {
                $id = get_class($this->context) . '#js#' . $count++;
                $this->registerJs(trim($match[4]), $pos, $id);
                return '';
            }
        } elseif ($match[1] === 'style') {
            $media = $match[3] ?: 'all';
            $this->cssSnippets[$media][] = trim($match[4]);
            return '';
        }
        return $match[0];
    }

    /**
     * 发布 Assets
     */
    protected function initAliases()
    {
        $am = $this->getAssetManager();
        $linkAssets = $am->linkAssets;
        $am->linkAssets = true;
        $result = $am->publish('@webroot/uploads');
        $am->linkAssets = $linkAssets;
        Yii::setAlias('@web/uploads', $result[1]);
    }
}
