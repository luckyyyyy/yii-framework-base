<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 基础分词封装
 * 按空格和特殊字符切分英文，中文按二元切割
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class BasicTokenizer implements Tokenizer
{
    /**
     * BasicTokenizer constructor.
     */
    public function __construct()
    {
        mb_internal_encoding('utf-8');
    }

    /**
     * @inheritdoc
     */
    public function parse($text)
    {
        $parts = preg_split('/\W+/u', mb_strtolower($text));
        $parts = array_filter($parts, 'trim');
        $tokens = [];
        foreach ($parts as $part) {
            preg_match_all('/(?:\w+|\W+)/', $part, $matches);
            foreach ($matches[0] as $ppart) {
                if ((ord(substr($ppart, 0, 1)) & 0x80) && ($len = mb_strlen($ppart)) > 1) {
                    for ($i = 0; $i < $len - 1; $i++) {
                        $tokens[] = mb_substr($ppart, $i, 2);
                    }
                } else {
                    $tokens[] = $ppart;
                }
            }
        }
        return $tokens;
    }
}
