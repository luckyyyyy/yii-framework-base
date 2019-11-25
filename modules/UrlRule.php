<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules;

use Yii;
use yii\helpers\Inflector;
use yii\web\CompositeUrlRule;

/**
 * 子模块 URL 规则集
 *
 * @author William Chan <root@williamchan.me>
 */
class UrlRule extends CompositeUrlRule
{
    /**
     * @var \yii\base\Module
     */
    public $module;

    /**
     * @var bool whether recursive processing sub-modules
     */
    public $recursive = true;

    /**
     * @inheritdoc
     */
    public function parseRequest($manager, $request)
    {
        $pathInfo = $request->pathInfo;
        foreach ($this->rules as $rule) {
            /* @var $rule \yii\web\UrlRule */
            $constName = $rule->name;
            if (($pos = strpos($constName, '<')) !== false) {
                $constName = substr($constName, 0, $pos);
            } else {
                $constName = rtrim($constName, '/');
            }

            if (strncmp($constName, $pathInfo, strlen($constName))) {
                continue;
            }
            $result = $rule->parseRequest($manager, $request);
            if (YII_DEBUG) {
                Yii::trace([
                    'rule' => method_exists($rule, '__toString') ? $rule->__toString() : get_class($rule),
                    'match' => $result !== false,
                    'parent' => self::class,
                ], __METHOD__);
            }
            if ($result !== false) {
                return $result;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function createRules()
    {
        if (YII_ENV_PROD) {
            $cacheKey = 'UrlRule.' . $this->module->uniqueId;
            $cache = Yii::$app->cache3;
            $rules = $cache->get($cacheKey);
            if ($rules === false) {
                $rules = $this->createModuleRules($this->module);
                $cache->set($cacheKey, $rules);
            }
            return $rules;
        } else {
            return $this->createModuleRules($this->module);
        }
    }

    /**
     * @param \yii\base\Module $module
     * @return array
     */
    protected function createModuleRules($module)
    {
        $rules = [];
        $config = ['class' => 'yii\web\UrlRule'];
        $routePrefix = $module->uniqueId;
        if (isset($module->host)) {
            $config['host'] = $module->host;
            $patternPrefix = $module->module ? $module->module->uniqueId : '';
        } else {
            $patternPrefix = $routePrefix;
        }
        //$config['suffix'] = '.json';

        $patterns = $this->buildPatterns($module);
        foreach ($patterns as $pattern => $action) {
            $pos = strpos($pattern, ' ');
            if ($pos !== false) {
                $config['verb'] = explode(',', substr($pattern, 0, $pos));
                $config['verb'][] = 'OPTIONS';
                $pattern = substr($pattern, $pos + 1);
            } else {
                unset($config['verb']);
            }
            $config['pattern'] = $patternPrefix . '/' . $pattern;
            if (is_array($action)) {
                $config['route'] = $routePrefix . '/' . array_shift($action);
                $config['defaults'] = $action;
            } else {
                $config['route'] = $routePrefix . '/' . $action;
                unset($config['defaults']);
            }
            $rules[] = Yii::createObject($config);
        }

        if ($this->recursive === true) {
            foreach (array_keys($module->modules) as $id) {
                $rules = array_merge($this->createModuleRules($module->getModule($id)), $rules);
            }
        }
        return $rules;
    }

    /**
     * 扫描控制器内定义的规则集
     * @param \yii\base\Module $module
     * @return array
     */
    protected function buildPatterns($module)
    {
        $patterns = [];
        $interfaceClass = __NAMESPACE__ . '\\UrlCustomizable';
        // part I.
        $controllerPath = $module->getControllerPath();
        if (is_dir($controllerPath)) {
            $files = scandir($controllerPath);
            foreach ($files as $file) {
                if (!empty($file) && substr_compare($file, 'Controller.php', -14, 14) === 0) {
                    $controllerClass = $module->controllerNamespace . '\\' . substr($file, 0, -4);
                    $class = new \ReflectionClass($controllerClass);
                    if (!$class->isAbstract() && $class->implementsInterface($interfaceClass)) {
                        $rules = call_user_func([$controllerClass, 'urlRules']);
                        $controller = Inflector::camel2id(substr($file, 0, -14));
                        foreach ($rules as $pattern => $action) {
                            if (is_array($action)) {
                                $action[0] = $controller . '/' . $action[0];
                            } else {
                                $action = $controller . '/' . $action;
                            }
                            $patterns[$pattern] = $action;
                        }
                    }
                }
            }
        }
        // part II.
        foreach ($module->controllerMap as $controller => $config) {
            $controllerClass = is_string($config) ? $config : $config['class'];
            $class = new \ReflectionClass($controllerClass);
            if ($class->implementsInterface($interfaceClass)) {
                $rules = call_user_func([$controllerClass, 'urlRules']);
                foreach ($rules as $pattern => $action) {
                    if (is_array($action)) {
                        $action[0] = $controller . '/' . $action[0];
                    } else {
                        $action = $controller . '/' . $action;
                    }
                    $patterns[$pattern] = $action;
                }
            }
        }
        return $patterns;
    }
}
