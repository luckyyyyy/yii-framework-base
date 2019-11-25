<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\user;

use app\components\Html;
use Yii;
use yii\base\BaseObject;
use yii\base\NotSupportedException;
use yii\helpers\Inflector;
use yii\rbac\CheckAccessInterface;

/**
 * 通用用户权限校验器
 *
 * @author William Chan <root@williamchan.me>
 */
class PermChecker extends BaseObject implements CheckAccessInterface
{
    /**
     * @var \app\models\Identity
     */
    public $identity;

    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        if ($userId !== $user->id) {
            throw new NotSupportedException('"' . __METHOD__ . '" only support current logined user.');
        } elseif ($user->isGuest && $permissionName !== 'limit') { // 游客限制所有权限 除了limit
            return false;
        } elseif ($user->isSuper) { // 超级管理员和root允许所有权限
            return true;
        }
        $methodName = 'can' . Inflector::id2camel($permissionName);
        if (method_exists($this, $methodName)) {
            $this->identity = $user->identity;
            return $this->$methodName($params);
        }
        return false;
    }

    /**
     * 通用的限制
     * @param array $params
     * @return bool|Closure
     */
    protected function canLimit($params = [])
    {
        $key = $params['key'] ?? 'global';
        $max = $params['max'] ?? 1;
        $second = $params['second'] ?? 10;
        $cacheKey = '__global_limit_' . $key . '_';
        $prefix = Yii::$app->user->id;
        if (isset($params['prefix'])) {
            $prefix = $params['prefix'];
        }
        $cacheKey .= $prefix;
        // package cachekey
        $cache = Yii::$app->cache2;
        $limit = $cache->get($cacheKey);
        if ($limit >= $max) {
            return false;
        } else {
            return function () use ($cache, $cacheKey, $limit, $second) {
                $cache->set($cacheKey, $limit + 1, $second);
            };
        }
    }
}
