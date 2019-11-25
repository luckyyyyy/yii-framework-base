<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */
namespace app\search;

/**
 * 话题搜索
 *
 * @property string $id
 * @property string $topic_name
 *
 * @author
 */
class CommunityTopic extends BaseSearch
{
    /**
     * 创建索引
     * @param int $id
     * @param string $topic_name
     * @return void
     */
    public static function createIndex($id, $topic_name)
    {
        $index = static::getDb()->index;
        (new static(['id' => $id, 'topic_name' => $topic_name]))->save(false);
        // 添加同义词
        $words = explode(' ', $topic_name);
        $synonym = '';
        foreach (array_reverse($words) as $i => $val) {
            $synonym = $val . $synonym;
            if ($i !== 0) {
                $index->addSynonym($synonym, $topic_name);
                // echo $synonym, ' - ', $name, PHP_EOL;
            }
        }
    }
}
