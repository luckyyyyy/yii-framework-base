<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */
namespace app\search;

/**
 * 帖子搜索
 *
 * @property string $id
 * @property string $content
 *
 * @author
 */
class CommunityPost extends BaseSearch
{
    /**
     * 创建索引
     * @param $id
     * @param $content
     * @param $room_id
     * @param $tags
     */
    public static function createIndex($id, $content, $room_id, $tags)
    {
        (new static(['id' => $id, 'content' => $content, 'room_id' => $room_id, 'tags' => $tags]))->save(false);
    }
}
