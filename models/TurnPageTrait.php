<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\models;

use Yii;

/**
 * 不通过ID分页
 *
 * @author fangjiali <root@fangjiali>
 */
trait TurnPageTrait
{
    private $_maxLimit = 1000000; // 单次请求最大条数，100万后不让你翻了
    private $_query;
    public $scene = 'wap'; //wap:移动端 admin:后台


    /**
     * @param $query
     * @param string $orderBy
     * @param string $sort
     * @param array $sort_condition
     * @param null $type
     * @return mixed
     */
    public function getPages($query, $orderBy = 'id', $sort = 'desc', $sort_condition = [], $type = null)
    {
        $page = \Yii::$app->request->get('page', 1) ?: 1;
        $limit = \Yii::$app->request->get('limit', 20) ?: 1;
        $this->_query = $query;

        $offset = ($page - 1) * $limit;
        if ($this->scene === 'wap') {
            $count = $this->findByExplainRows();
        } else {
            $count = $this->getCount();
        }
        if ($type) {
            $all['result'] = $query->offset($offset)->limit($limit)->orderBy($sort_condition)->all();
        } else {
            $all['result'] = $query->offset($offset)->limit($limit)->orderBy("{$orderBy} {$sort}")->all();
        }

        $all['extra'] = ['count' => $count, 'page' => $page];
        return $all;
    }

    /**
     * 分页
     * @param int $sort 排序方式
     * @param string $pk 排序主键
     * @return array
     */
    public function findByOffset($query, $sort = SORT_DESC, $pk = 'id')
    {
        $this->_query = $query;
        $extra = [];
        $req = Yii::$app->request;
        $point = (int)$req->get('point');
        $page = (int)$req->get('page', 1) ?: 1;
        $limit = (int)$req->get('limit', 20) ?: 1;
        if (empty($limit) || $limit > 30) {
            $limit = 30;
        }
        $op = $sort === SORT_DESC ? '<' : '>';
        // null 和 0不区分 默认走瀑布流 节省开销
        if (empty($page) || !empty($point) || empty($page) && empty($point)) {
            if (!empty($point)) {
                $query->andWhere([$op, $pk, $point]);
            }
            $extra['use'] = 'point';
        } else {
            if ($page * $limit >= $this->_maxLimit) {
                $page = 1;
            }

            if ($this->scene === 'wap') {
                $count = $this->findByExplainRows();
            } else {
                $count = $this->getCount();
            }

            $offset = ($page - 1) * $limit;
            $query->offset($offset);
            $extra['count'] = $count;
            $extra['use'] = 'page';
        }
        $query->limit($limit);
        $result = $query->orderBy([$pk => $sort])->all();
        // @fixme 这里可能会出现rows为1的情况
        if (isset($extra['count']) && $extra['count'] === 1 && count($result) === 0) {
            $extra['count'] = 0;
        }
        if (!isset($extra['count'])) {
            $extra['point'] = end($result) ? end($result)[$pk] : 0;
        }
        return [
            'extra' => $extra,
            'result' => $result,
        ];
    }


    /**
     * 获取预估的总数
     * @return int
     */
    public function findByExplainRows(): int
    {
        // 创建一个 command 对象 取预估影响的rows就可以了
        $command = $this->_query->createCommand();
        $command->rawSql = 'EXPLAIN ' . $command->rawSql;
        $explain = $command->queryOne();
        return (int)$explain['rows'];
    }

    /**
     * 获取准确的count
     * @return mixed
     */
    public function getCount(): int
    {
        return $this->_query->count();
    }
}
