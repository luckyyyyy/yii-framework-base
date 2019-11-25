<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\models;

use Yii;
use yii\base\BaseObject;

/**
 * 对象筛选器（抽象）
 *
 * @property-read string $id
 * @property-read string $title 随便写个标题 万一有用呢
 * @property-read \yii\db\ActiveQuery $query
 * @property \app\components\User $user
 *
 * @author William Chan <root@williamchan.me>
 */
abstract class BaseFilter extends BaseObject
{
    /**
     * @var string 额外参数
     */
    public $extra;

    protected $_pointAttribute = false;
    protected $_result = [];
    private $_name;
    private $_user;
    private $_isOffset = true;

    /**
     * 获取标题
     * @return string
     */
    abstract public function getTitle();

    /**
     * 是否可用
     * @return bool
     */
    public function getIsActive()
    {
        return true;
    }

    /**
     * 获取使用者
     * @return \app\components\User
     */
    public function getUser()
    {
        return $this->_user ? $this->_user : Yii::$app->user;
    }

    /**
     * 设置使用者
     * @param \app\components\User $user
     * @return static
     */
    public function setUser($user)
    {
        $this->_user = $user;
        return $this;
    }

    /**
     * 获取标识
     * @return string
     */
    public function getId()
    {
        $className = get_class($this);
        if (preg_match('/([A-Z][a-z0-9]+)[A-Z][a-z]+$/', $className, $match)) {
            return strtolower($match[1]);
        }
        return null;
    }

    /**
     * 获取名称
     * @return string
     */
    public function getName()
    {
        if ($this->_name === null) {
            $this->_name = $this->getId();
        }
        return $this->_name;
    }

    /**
     * 获取排序属性
     * @param bool $raw 是否保留原始别名
     * @return string
     */
    public function getPointAttribute($raw = false)
    {
        if ($raw === true) {
            $query = $this->getQuery();
            foreach ($query->orderBy as $key => $value) {
                if (is_string($key)) {
                    return $key;
                }
            }
            return null;
        }
        if ($this->_pointAttribute === false) {
            $key = $this->getPointAttribute(true);
            if ($key !== null && ($pos = strrpos($key, '.')) !== false) {
                $key = substr($key, $pos + 1);
            }
            $this->_pointAttribute = $key;
        }
        return $this->_pointAttribute;
    }

    /**
     * 是否排序升序
     * @return bool
     */
    public function getPointAscending()
    {
        $query = $this->getQuery();
        foreach ($query->orderBy as $key => $value) {
            if (is_string($key)) {
                return $value === SORT_ASC;
            }
        }
        return false;
    }

    /**
     * 设置搜索关词
     * @param string $kw
     * @return static
     */
    abstract public function search($kw);

    /**
     * 设置分页偏移量
     * @param int $offset
     * @return static
     */
    public function offset($offset)
    {
        if ($offset !== null && $offset > 20000) {
            $offset = 20000;
        }
        $this->getQuery()->offset($offset);
        return $this;
    }

    /**
     * 设置分页数量
     * @param int $limit
     * @return static
     */
    public function limit($limit)
    {
        $limit = min($limit, 50);
        $this->getQuery()->limit($limit);
        return $this;
    }

    /**
     * 设置起始点以加速
     * @param string|int $point
     * @return static
     */
    public function point($point)
    {
        if ($point !== null) {
            $attribute = $this->getPointAttribute(true);
            if ($attribute !== null) {
                $this->_isOffset = false;
                $cmp = $this->getPointAscending() ? '>' : '<';
                $this->getQuery()->offset(0)->andWhere([$cmp, $attribute, $point]);
            }
        }
        return $this;
    }

    /**
     * 自动填充分页属性
     * @return static
     */
    public function loadPageParams()
    {
        $req = Yii::$app->request;
        // point 只有数字的才可以 其他的根绝ID取值是流氓
        $point = (int) $req->get('point');
        $page = abs((int) $req->get('page'));
        $limit = abs((int) $req->get('limit'));
        if (empty($limit)) {
            $limit = 20;
        }
        $limit = min($limit, 50);
        if (empty($page) || $point) {
            if (!empty($point)) {
                $this->point($point);
            }
        } else {
            $offset = ($page - 1) * $limit;
            $this->offset($offset);
        }
        $this->limit($limit);

        return $this;
    }

    /**
     * 是否是分页
     * @return bool
     */
    public function getIsOffset()
    {
        return $this->_isOffset;
    }

    /**
     * 格式化输出统一的 extra 信息
     * @param bool $useAccurateCount 是否使用准确的数据 count
     * @return array
     */
    public function getOffsetExtra($useAccurateCount = false)
    {
        $extra = [];
        $extra['use'] = $this->isOffset ? 'offset' : 'point';
        if ($this->isOffset) {
            if ($useAccurateCount) {
                $extra['count'] = $this->count();
            } else {
                $extra['count'] = $this->explainRows();
            }
            // @fixme 这里可能会出现rows为1的情况 explain最低行数是1
            if ($extra['count'] === 1 && count($this->_result) === 0) {
                $extra['count'] = 0;
            }
            $query = $this->getQuery();
            $extra['page'] = $query->offset / $query->limit + 1;
        } else {
            $attribute = $this->getPointAttribute(true);
            $extra['point'] = end($this->_result) ? end($this->_result)[$attribute] : 0;
        }
        return $extra;
    }

    /**
     * 获取数据总量
     * @return int
     */
    public function count() : int
    {
        $query = clone($this->getQuery());
        $query->orderBy = null;
        $query->limit = null;
        return $query->count();
    }

    /**
     * 获取预估的总数
     * @return int
     */
    public function explainRows() : int
    {
        $query = clone($this->getQuery());
        $query->orderBy = null;
        $query->limit = null;
        $command = $query->createCommand();
        $command->rawSql = 'EXPLAIN ' . $command->rawSql;
        $explain = $command->queryOne();
        return $explain['rows'] ?? 0;
    }

    /**
     * 获取数据列表
     * 需要给 _result 赋值，这样可以使用 $this->offsetExtra 来获取分页的 extra 信息
     * 常规做法参考下面的例子
     *
     * ```php
     * public function models()
     * {
     *     $this->_result = $this->getQuery()->all();
     *     return $this->_result;
     * }
     * ```
     *
     * @return \yii\db\ActiveRecord[]
     */
    abstract public function models();

    /**
     * @return \yii\db\ActiveQuery
     */
    abstract public function getQuery();

    /**
     * 创建过滤器
     * @param string $name
     * @return static
     */
    abstract public static function create($name = null);
}
