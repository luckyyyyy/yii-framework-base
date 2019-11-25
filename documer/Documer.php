<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 贝叶斯文档自动分类
 *
 * @see https://github.com/kbariotis/documer
 * @author hightman <hightman@cloud-sun.com>
 */
class Documer
{
    /**
     * @var Storage 存储器
     */
    public $storage;

    /**
     * @var Tokenizer 分词器
     */
    public $tokenizer;

    /**
     * 构造函数
     * @param Storage $storage
     * @param Tokenizer $tokenizer
     * @throws \Exception
     */
    public function __construct(Storage $storage = null, Tokenizer $tokenizer = null)
    {
        $this->storage = $storage === null ? new MemoryStorage() : $storage;
        $this->tokenizer = $tokenizer === null ? new BasicTokenizer() : $tokenizer;
    }

    /**
     * 重置语料库
     */
    public function reset()
    {
        $this->storage->reset();
    }

    /**
     * 添加停用词
     * @param string $word
     */
    public function addStopword($word)
    {
        $this->storage->addStopword($word);
    }

    /**
     * 添加训练语料
     * @param string $label 标签
     * @param string $text 样文语料
     */
    public function train($label, $text)
    {
        $tokens = $this->tokenizer->parse($text);
        if (count($tokens) > 0) {
            $this->storage->insertDoc($label, $tokens);
        }
    }

    /**
     * 检查文本是否最大概率分类
     * @param string $label
     * @param string $text
     * @return bool
     */
    public function is($label, $text)
    {
        $scores = $this->guess($text);
        return $label == key($scores);
    }

    /**
     * 文本分类概率（贝叶斯定理）
     * @param string $text 要计算的文本
     * @return array 符合各标签的概率 {label: probability}
     */
    public function guess($text)
    {
        $scores = [];
        $tokens = $this->tokenizer->parse($text);
        $labels = $this->storage->getLabels();
        $docTotal = $this->storage->getTotalDocs();
        foreach ($labels as $label) {
            $logSum = 0;
            $docCount = $this->storage->getTotalDocsWithLabel($label);
            $inversedDocCount = $docTotal - $docCount;
            if (0 === $inversedDocCount) {
                continue;
            }
            foreach ($tokens as $token) {
                $tokenTotal = $this->storage->getTokenCount($token);
                if (0 === $tokenTotal) {
                    continue;
                }
                $tokenCount = $this->storage->getTokenCountWithLabel($token, $label);
                $inversedTokenCount = $tokenTotal - $tokenCount;
                $tokenProbabilityPositive = $tokenCount / $docCount;
                $tokenProbabilityNegative = $inversedTokenCount / $inversedDocCount;
                $probability = $tokenProbabilityPositive / ($tokenProbabilityPositive + $tokenProbabilityNegative);
                $probability = ((1 * 0.5) + ($tokenTotal * $probability)) / (1 + $tokenTotal);
                if (0 === $probability) {
                    $probability = 0.01;
                } elseif (1 === $probability) {
                    $probability = 0.99;
                }
                $logSum += log(1 - $probability) - log($probability);
            }
            $scores[$label] = 1 / (1 + exp($logSum));
        }
        arsort($scores, SORT_NUMERIC);
        return $scores;
    }
}
