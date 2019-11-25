<?php
/**
 * This file is part of the haimanchajian.
 * @link http://haiman.io/
 * @copyright Copyright (c) 2016 Hangzhou Haila Information Technology Co., Ltd
 */
namespace app\documer;

/**
 * 将训练数据保存在文件里
 *
 * @author hightman <hightman@cloud-sun.com>
 */
class FileStorage extends MemoryStorage
{
    /**
     * @var int 数据变化次数
     */
    protected $dataChanges = 0;
    private $_dataFile;

    /**
     * 构造函数
     * @param string $filename 数据保存路径
     */
    public function __construct($filename)
    {
        $this->_dataFile = $filename;
        if (file_exists($filename)) {
            $this->restore();
        }
    }

    /**
     * 析构函数，自动存储
     */
    public function __destruct()
    {
        if ($this->dataChanges > 0) {
            $this->store();
        }
    }

    /**
     * @inheritdoc
     */
    public function insertDoc($label, $tokens)
    {
        parent::insertDoc($label, $tokens);
        $this->dataChanges++;
        if ($this->dataChanges > 100) {
            $this->store();
        }
    }

    /**
     * @inheritdoc
     */
    public function addStopword($word)
    {
        parent::addStopword($word);
        $this->dataChanges++;
    }

    /**
     * 还原训练数据
     */
    protected function restore()
    {
        $data = unserialize(file_get_contents($this->_dataFile));
        $this->labels = $data['labels'];
        $this->tokens = $data['tokens'];
        if (isset($data['stops'])) {
            $this->stops = $data['stops'];
        }
        $this->dataChanges = 0;
    }

    /**
     * 存储训练数据
     */
    protected function store()
    {
        $this->dataChanges = 0;
        file_put_contents($this->_dataFile, serialize([
            'labels' => $this->labels,
            'tokens' => $this->tokens,
            'stops' => $this->stops]));
    }
}
