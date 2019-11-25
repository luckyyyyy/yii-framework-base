<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\controllers;

use app\components\Muggle;
use Yii;
use yii\base\InlineAction;
use yii\base\ViewNotFoundException;
use yii\web\Controller;

/**
 * 多客户端智能切换
 *
 * - 在移动端或移动端域名下，优先尝试 {action}-mob
 * - 在微信中优先尝试 {action}-wx, {action}-mob
 *
 * @author William Chan <root@williamchan.me>
 */
abstract class MultiAgent extends Controller
{
    const MULTI_NONE = 0;
    const MULTI_VIEW = 1;
    const MULTI_ACTION = 2;

    private $_agentChains;

    /**
     * 多客户端定义
     * 列出需要智能切换的 action id 及切换类型
     * @return array
     */
    abstract public function mActions();

    /**
     * @param \yii\base\Action $action
     * @return bool
     */
    protected function isMultiAction($action)
    {
        $map = $this->mActions();
        return isset($map[$action->id]) && $map[$action->id] === self::MULTI_ACTION;
    }

    /**
     * @param \yii\base\Action $action
     * @return bool
     */
    protected function isMultiView($action)
    {
        $map = $this->mActions();
        return isset($map[$action->id]) && $map[$action->id] === self::MULTI_VIEW;
    }

    /**
     * return array 多客户端处理链
     */
    protected function getAgentChains()
    {
        if ($this->_agentChains === null) {
            $this->_agentChains = [];
            if (Muggle::isWechat()) {
                $this->_agentChains[] = 'wx';
            }
            if (Muggle::isMobile()) {
                $this->_agentChains[] = 'mob';
            } else {
                $params = Yii::$app->params;
                if (isset($params['wechat.host.app'], $params['wechat.host.web'])
                    && $params['wechat.host.app'] !== $params['wechat.host.web']
                    && $params['wechat.host.app'] === Yii::$app->request->hostName
                ) {
                    $this->_agentChains[] = 'mob';
                }
            }
        }
        return $this->_agentChains;
    }

    /**
     * @inheritdoc
     */
    public function createAction($id)
    {
        $action = parent::createAction($id);
        if ($action instanceof InlineAction && $this->isMultiAction($action)) {
            foreach ($this->getAgentChains() as $chain) {
                $methodName = $action->actionMethod . ucfirst($chain);
                if (method_exists($this, $methodName)) {
                    $action->id = $action->id . '-' . $chain;
                    $action->actionMethod = $methodName;
                    break;
                }
            }
        }
        return $action;
    }

    /**
     * @inheritdoc
     */
    public function render($view, $params = [])
    {
        if ($this->isMultiView($this->action)) {
            foreach ($this->getAgentChains() as $chain) {
                try {
                    return parent::render($view . '_' . $chain, $params);
                } catch (ViewNotFoundException $e) {
                }
            }
        }
        return parent::render($view, $params);
    }
}
