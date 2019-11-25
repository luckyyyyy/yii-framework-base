<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Composer\Installer\LibraryInstaller;
use Composer\Installer\PackageEvent;
use Composer\Script\Event;

/**
 * Composer 安装/更新事件处理
 *
 * @author William Chan <root@williamchan.me>
 */
class Installer extends LibraryInstaller
{

    /**
     * 项目安装之后的操作
     * - 创建 config/custom.php
     * - 提示编辑配置文件
     *
     * @param \Composer\Script\Event $event
     */
    public static function postInstall(Event $event)
    {
        $customFile = 'config/custom.php';
        if (!file_exists($customFile)) {
            $sampleFile = 'config/custom-sample.php';
            echo 'copy("', $sampleFile, '", "', $customFile, '")...';
            copy($sampleFile, $customFile);
            echo 'done.', PHP_EOL;
        }
        echo '**Note** Please modify `config/custom.php\' according to your needs.', PHP_EOL;
    }
}
