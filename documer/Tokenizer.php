<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 分词器接口定义
 *
 * @author hightman <hightman@cloud-sun.com>
 */
interface Tokenizer
{
    /**
     * 切词方法
     * @param string $text 要切分的文本
     * @return string[] 切分后的词表
     */
    public function parse($text);
}
