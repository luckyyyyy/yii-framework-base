<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveQuery;

/**
 * 简易分页逻辑处理公共代码 支持瀑布流和分页
 *
 * @author William Cham <root@williamchan.me>
 */
class PageQuery extends ActiveQuery
{
    /**
     * @var int 单次请求最大条数
     */
    private $_maxLimit = 1000000; // 100万后不让你翻了

    /**
     * 分页
     * @param int $sort 排序方式
     * @param string $pk 排序主键
     * @return array
     */
    public function findByOffset($sort = SORT_DESC, $pk = 'id')
    {
        $result = [];
        $extra = [];
        $req = Yii::$app->request;
        $point = (int) $req->get('point');
        $page = (int) $req->get('page');
        $limit = (int) $req->get('limit');
        if (empty($limit) || $limit > 30) {
            $limit = 30;
        }
        $op = $sort === SORT_DESC ? '<' : '>';
        // null 和 0不区分 默认走瀑布流 节省开销
        if (empty($page) || !empty($point) || empty($page) && empty($point)) {
            if (!empty($point)) {
                $this->andWhere([$op, $pk, $point]);
            }
            $extra['use'] = 'point';
        } else {
            if ($page * $limit >= $this->_maxLimit) {
                $page = 1;
            }
            $count = $this->findByExplainRows();
            $offset = ($page - 1) * $limit;
            $this->offset($offset);
            $extra['count'] = $count;
            $extra['use'] = 'page';
        }
        $this->limit($limit);
        $result = $this->orderBy([$pk => $sort])->all();
        // @fixme 这里可能会出现rows为1的情况
        if (isset($extra['count']) && $extra['count'] === 1  && count($result) === 0) {
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
    public function findByExplainRows() : int
    {
        // 创建一个 command 对象 取预估影响的rows就可以了
        $command = $this->createCommand();
        $command->rawSql = 'EXPLAIN ' . $command->rawSql;
        $explain = $command->queryOne();
        return (int)$explain['rows'];
    }
}
