<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 存储器接口定义
 *
 * @author hightman <hightman@cloud-sun.com>
 */
interface Storage
{
    /**
     * @return array 标签列表
     */
    public function getLabels();

    /**
     * @return int 获取语料总数
     */
    public function getTotalDocs();

    /**
     * 获取某个标签的语料总数
     * @param string $label
     * @return int
     */
    public function getTotalDocsWithLabel($label);

    /**
     * 获取词根在语料中的总出现次数
     * @param string $token
     * @return int
     */
    public function getTokenCount($token);

    /**
     * 获取词根在某个标签的语料中的出现次数
     * @param string $token
     * @param string $label
     * @return int
     */
    public function getTokenCountWithLabel($token, $label);

    /**
     * 添加语料
     * @param string $label
     * @param array $tokens
     */
    public function insertDoc($label, $tokens);

    /**
     * 添加停用词
     * @param string $word
     */
    public function addStopword($word);

    /**
     * 重设数据
     */
    public function reset();
}
