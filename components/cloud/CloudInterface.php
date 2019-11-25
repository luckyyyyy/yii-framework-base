<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\components\cloud;

use Yii;

/**
 * 外部云服务接口
 *
 * @author hightman <hightman@cloud-sun.com>
 */
interface CloudInterface
{

    /**
     * 鉴别黄图
     * @param string|array $url 要鉴定的本地网址
     * @return int 鉴定结果：0=正常/1=黄图/2=疑似
     */
    public function pornDetect($url);

    /**
     * 场景识别
     * @param string|array $url 网址
     * @return array
     */
    public function imageScene($url);


    /**
     * 图像打标
     * @param string|array $url 网址
     * @return array
     */
    public function imageTag($url);

    /**
     * 文本敏感度分析
     * @param string $content
     * @param int $type 类型，1=色情/2=政治
     * @return float 敏感的概率(0~1)
     */
    public function textSensitivity($content, $type = 1);

    /**
     * 文本关键词分析
     * @param string $content 内容
     * @param string $title 标题
     * @return array 关键词列表
     */
    public function textKeywords($content, $title = null);
}
