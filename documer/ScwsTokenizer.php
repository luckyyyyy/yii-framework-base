<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * SCWS 分词封装
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class ScwsTokenizer implements Tokenizer
{
    public $scws;

    /**
     * 构造函数，创建 scws 分词实例
     */
    public function __construct()
    {
        $this->scws = scws_new('utf8');
        $this->scws->set_ignore(true);
        $this->scws->set_duality(true);
        $this->scws->set_multi(3);
    }

    /**
     * @inheritdoc
     */
    public function parse($text)
    {
        $tokens = [];
        $this->scws->send_text($text);
        while ($words = $this->scws->get_result()) {
            foreach ($words as $word) {
                $tokens[] = $word['word'];
            }
        }
        return $tokens;
    }
}
