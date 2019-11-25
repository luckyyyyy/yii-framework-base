<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components\storage;

/**
 * 阿里云OSS或奇葩云接口定义
 * 考虑以后实现成stream wrapper
 *
 * @author William Chan <root@williamchan.me>
 */
interface StorageInterface
{
    /**
     * @return string 最后的出错信息
     */
    public function getLastError();

    /**
     * put object
     * @param string $path
     * @param string $content 文件内容或http开头的文件
     * @param array $header 额外的header 暂未实现
     * @return bool
     */
    public function putObject($path, $content, $Header = null);

    /**
     * Head Object
     * @param string $path 文件路径
     * @param array $Header 额外的header 暂未实现
     * @return bool
     */
    public function headObject($path, $Header = null);

    /**
     * copy object
     * @param string $path 文件路径
     * @param string $source 原始路径
     * @param array $Header 额外的header 暂未实现
     * @return bool
     */
    public function copyObject($path, $source, $Header = null);

    /**
     * delete object
     * @param string $path
     * @return bool
     */
    public function deleteObject($path, $Header = null);

    /**
     * 获取 BucketHost
     * @return string
     */
    public function getHost();
}
