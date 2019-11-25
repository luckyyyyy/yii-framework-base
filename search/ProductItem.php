<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\search;

/**
 * 产品库搜索
 *
 * @property string $id
 * @property string $name
 *
 * @author
 */
class ProductItem extends BaseSearch
{
    /**
     * 创建索引
     * @param int $id
     * @param string $name
     * @param string $era
     * @return void
     */
    public static function createIndex($id, $name, $era)
    {
        $index = static::getDb()->index;
        (new static(['id' => $id, 'name' => $name, 'era' => $era]))->save(false);
        // 添加同义词
        $words = explode(' ', $name);
        $synonym = '';
        foreach (array_reverse($words) as $i => $val) {
            $synonym = $val . $synonym;
            if ($i !== 0) {
                $index->addSynonym($synonym, $name);
                // echo $synonym, ' - ', $name, PHP_EOL;
            }
        }
    }
}
