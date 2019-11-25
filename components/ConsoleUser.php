<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

/**
 * 控制台 User
 * 用于部分需要强依赖用户身份的代码逻辑，默认是一个游客身份的用户。
 * @author William Chan <root@williamchan.me>
 */
class ConsoleUser extends \yii\base\BaseObject
{
    public $id;
    private $_isAdmin = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * 是否是游客用户
     * @return bool
     */
    public function getIsGuest()
    {
        return true;
    }

    /**
     * 获取用户ID
     * @return null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 设置当前用户ID
     * 除非你知道自己在做什么 不要轻易设置
     * @return void
     */
    public function setId()
    {
        $this->id = $id;
    }

    /**
     * 是否是管理员
     * @return bool
     */
    public function isAdmin($module = null)
    {
        return $this->_isAdmin;
    }

    /**
     * 设置当前用户是否是管理员
     * 除非你知道自己在做什么 不要轻易设置
     * @return bool
     */
    public function setIsAdmin(bool $value)
    {
        $this->_isAdmin = $value;
    }

    /**
     * @todo
     * @return void
     */
    public function getIdentity()
    {
    }

    /**
     * @todo
     * @return void
     */
    public function setIdentity()
    {
    }

    /**
     * @todo
     * @return void
     */
    public function setUser($user)
    {
    }
}
