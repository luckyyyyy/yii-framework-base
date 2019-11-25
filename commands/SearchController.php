<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use app\components\Html;
use app\models\Timeout;
use app\modules\community\models\Post;
use app\search\CommunityPost;
use app\search\CommunityTopic;
use app\search\ProductItem;
use Yii;
use yii\db\Query;

/**
 * 迁移工具
 * @author William Chan <root@williamchan.me>
 */
class SearchController extends Controller
{
    /**
     * 给item库建立索引 xunsearch
     */
    public function actionItem()
    {
        try {
            $index = ProductItem::getDb()->index;
            echo 'Rebuilding ', ProductItem::projectName(), '.', PHP_EOL;
            $index->beginRebuild();
            $index->setScwsMulti(7);
            $index->openBuffer();
            echo '  > ';
            $query = (new Query())->from(ProductItem::projectName())->where(['group_id' => 0]);
            foreach ($query->batch(100) as $rows) {
                foreach ($rows as $row) {
                    ProductItem::createIndex($row['id'], $row['name'], $row['era']);
                }
            }
            echo PHP_EOL, 'Flushing index .';
            $index->closeBuffer();
            $index->endRebuild();
            for ($i = 0; $i < 3; $i++) {
                sleep(1);
                echo '.';
            }
            $index->flushLogging();
            echo 'OK', PHP_EOL;
        } catch (\Exception $e) {
            if (isset($index)) {
                $index->stopRebuild();
            }
            echo '  > ERROR: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString(), PHP_EOL;
        }
    }

    /**
     * 重建帖子索引
     */
    public function actionRebuildPost()
    {
        try {
            $index = CommunityPost::getDb()->index;
            echo 'Rebuilding ', CommunityPost::projectName(), '.', PHP_EOL;
            $index->beginRebuild();
            $index->setScwsMulti(7);
            $index->openBuffer();
            echo '  > ';
            $maxId = 0;
            $query = (new Query())->from(CommunityPost::projectName());
            foreach ($query->batch(100) as $rows) {
                echo '.';
                foreach ($rows as $row) {
                    CommunityPost::createIndex($row['id'], $row['content'], $row['room_id'], $row['tags']);
                    $maxId = $row['id'];
                }
            }

            echo PHP_EOL, 'Saving max id ...';
            Timeout::put('post_index_pos', 864000, $maxId);

            echo PHP_EOL, 'Flushing index .';
            $index->closeBuffer();
            $index->endRebuild();
            for ($i = 0; $i < 3; $i++) {
                sleep(1);
                echo '.';
            }
            $index->flushLogging();
            echo 'OK', PHP_EOL;
        } catch (\Exception $e) {
            if (isset($index)) {
                $index->stopRebuild();
            }
            echo '  > ERROR: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString(), PHP_EOL;
        }
    }

    /**
     * 给topic库建立索引 xunsearch
     */
    public function actionTopic()
    {
        try {
            $index = CommunityTopic::getDb()->index;
            echo 'Rebuilding ', CommunityTopic::projectName(), '.', PHP_EOL;
            $index->beginRebuild();
            $index->setScwsMulti(7);
            $index->openBuffer();
            echo '  > ';
            $query = (new Query())->from(CommunityTopic::projectName());
            foreach ($query->batch(100) as $rows) {
                echo '.';
                foreach ($rows as $row) {
                    CommunityTopic::createIndex($row['id'], $row['topic_name']);
                }
            }
            echo PHP_EOL, 'Flushing index .';
            $index->closeBuffer();
            $index->endRebuild();
            for ($i = 0; $i < 3; $i++) {
                sleep(1);
                echo '.';
            }
            $index->flushLogging();
            echo 'OK', PHP_EOL;
        } catch (\Exception $e) {
            if (isset($index)) {
                $index->stopRebuild();
            }
            echo '  > ERROR: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString(), PHP_EOL;
        }
    }

    /**
     * 帖子增量索引
     */
    public function actionPost()
    {
        $pid = $this->processId();
        if ($pid !== 0 && $pid !== getmypid()) {
            return Yii::info('search indexer locked. [' . $pid . ']');
        }

        // fetch last positions
        echo 'Loading last states ... ';
        $maxId = (int) Timeout::get('post_index_pos');
        echo 'max=', $maxId;
        $redis = Yii::$app->get('redis2');
        /* @var $redis \yii\redis\Connection */
        $changes = [];
        $qkey = 'post_changes';
        $llen = (int) $redis->llen($qkey);
        if ($llen > 0) {
            $items = $redis->lrange($qkey, 0, $llen - 1);
            foreach ($items as $item) {
                if ($item <= $maxId) {
                    $changes[] = $item;
                }
            }
        }
        echo ', changes=', count($changes), PHP_EOL;

        try {
            $db = CommunityPost::getDb();
            $index = $db->index;
            $index->setScwsMulti(7);
            $index->openBuffer();

            $count = 0;
            // update changed
            if (count($changes) > 0) {
                echo 'Updating changed posts ...', PHP_EOL;
                $models = Post::find()->where(['id' => $changes])->all();
                foreach ($models as $model) {
                    CommunityPost::createIndex($model->id, $model->content, $model->room_id, $model->tags);
                    $count++;
                }
            }
            // add new
            echo 'Adding new posts ... count=', Post::find()->where(['>', 'id', $maxId])->count(), PHP_EOL;
            echo '  > ';
            $query = Post::find()->with(['user'])->where(['>', 'id', $maxId])->orderBy(['id' => SORT_ASC]);
            foreach ($query->batch(1000) as $models) {
                echo '.';
                foreach ($models as $model) {

                    //获取图片tag存入post
                    $value = [];
                    foreach (Html::extUrl($model->images) as $key => $url) {
                        $result = Yii::$app->cloud->imageTag($url);
                        if (is_array($result)) {
                            foreach ($result as $item) {
                                if ($item['confidence'] >= 80) {
                                    if (!in_array($item['value'], $value)) {
                                        $value[] = $item['value'];
                                    }
                                }
                            }
                        }
                    }
                    $tags = implode(' ', $value);
                    Post::updateAll(['tags' => $tags], ['id' => $model->id]);

                    CommunityPost::createIndex($model->id, $model->content, $model->room_id, $tags);
                    $maxId = $model->id;
                    $count++;
                }
            }

            echo PHP_EOL, 'Saving index states ...';
            $index->closeBuffer();
            $index->flushIndex();
            if ($llen > 0) {
                $redis->ltrim($qkey, $llen, -1);
            }
            Timeout::put('post_index_pos', 864000, $maxId);
            echo PHP_EOL, 'DONE', PHP_EOL;
        } catch (\Exception $e) {
            echo '  > ERROR: ', $e->getMessage(), PHP_EOL;
            echo $e->getTraceAsString(), PHP_EOL;
        }
    }
}
