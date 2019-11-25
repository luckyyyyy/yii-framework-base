<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\mobile;

use app\components\Muggle;
use Yii;
use yii\helpers\Url;
use yii\web\ServerErrorHttpException;

/**
 * 移动端前端静态文件入口
 * @author William Chan <root@williamchan.me>
 */
class Module extends \yii\base\Module
{
    /**
     * @var string 本地资源路径
     */
    public $assetPath = '@localhost/static';

    /**
     * @inheritdoc
     */
    public function createController($route)
    {
        Yii::$app->user->isGuest; // save referral & login by token
        $useHash = false;
        if ($route !== '' && $useHash) {
            $url = Url::to(['/' . $this->uniqueId]) . '/#/' . $route;
            if (isset($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }
            Yii::$app->response->redirect($url);
        } else {
            $htmlDir = Yii::getAlias($this->assetPath);
            $htmlFile = $htmlDir . '/index.html';
            // if (!file_exists($htmlFile)) {
            //     $htmlFile = $htmlDir . '/' . (Muggle::isMobile() ? 'm' : 'pc') . '.html';
            // }
            if (!file_exists($htmlFile)) {
                throw new ServerErrorHttpException('Resource is not ready.');
            }
            /**
             * Reads a file and writes it to the output buffer.
             *
             * @see http://php.net/manual/en/function.readfile.php
             * readfile() will not present any memory issues, even when sending large files, on its own.
             * If you encounter an out of memory error ensure that output buffering is off with ob_get_level().
             */
            readfile($htmlFile);
        }
        Yii::$app->end();
    }
}
