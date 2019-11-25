<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 将训练数据保存在内存中
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class MemoryStorage implements Storage
{
    protected $labels = [];
    protected $tokens = [];
    protected $stops = [];

    /**
     * @inheritdoc
     */
    public function getLabels()
    {
        return array_keys($this->labels);
    }

    /**
     * @inheritdoc
     */
    public function getTotalDocs()
    {
        return array_sum($this->labels);
    }

    /**
     * @inheritdoc
     */
    public function getTotalDocsWithLabel($label)
    {
        return isset($this->labels[$label]) ? $this->labels[$label] : 0;
    }

    /**
     * @inheritdoc
     */
    public function getTokenCount($token)
    {
        return isset($this->tokens[$token]) ? array_sum($this->tokens[$token]) : 0;
    }

    /**
     * @inheritdoc
     */
    public function getTokenCountWithLabel($token, $label)
    {
        return isset($this->tokens[$token][$label]) ? $this->tokens[$token][$label] : 0;
    }

    /**
     * @inheritdoc
     */
    public function insertDoc($label, $tokens)
    {
        // total docs with label
        if (!isset($this->labels[$label])) {
            $this->labels[$label] = 1;
        } else {
            $this->labels[$label]++;
        }
        // tokens data
        foreach ($tokens as $token) {
            if (isset($this->stops[$token])) {
                continue;
            } elseif (!isset($this->tokens[$token][$label])) {
                $this->tokens[$token][$label] = 1;
            } else {
                $this->tokens[$token][$label]++;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function addStopword($word)
    {
        $this->stops[$word] = true;
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->tokens = $this->labels = [];
    }
}
