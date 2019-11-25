<?php
/*
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\search;

use hightman\xunsearch\ActiveRecord;
use Yii;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * 搜索基类
 * 截获修改配置文件的路径、搜索服务器信息
 *
 * @author William Chan <root@williamchan.me>
 */
abstract class BaseSearch extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function projectName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        $search = Yii::$app->get('xunsearch');
        /* @var $search \hightman\xunsearch\Connection */

        // hook ini path
        $savedDirectory = $search->iniDirectory;
        $search->iniDirectory = __DIR__ . '/config';
        $db = parent::getDb();
        $search->iniDirectory = $savedDirectory;

        // hook search server
        $params = Yii::$app->params;
        if (isset($params['xs.server.index']) || isset($params['xs.server.server'])) {
            $ref = new \ReflectionClass($db->xs);
            $pro = $ref->getProperty('_config');
            $pro->setAccessible(true);
            $_config = $pro->getValue($db->xs);
            $hasChanged = false;
            if (!isset($_config['server.index']) || $_config['server.index'] !== $params['xs.server.index']) {
                $_config['server.index'] = $params['xs.server.index'];
                $hasChanged = true;
            }
            if (!isset($_config['server.search']) || $_config['server.search'] !== $params['xs.server.search']) {
                $_config['server.search'] = $params['xs.server.search'];
                $hasChanged = true;
            }
            if ($hasChanged === true) {
                $pro->setValue($db->xs, $_config);
            }
        }
        return $db;
    }
}
