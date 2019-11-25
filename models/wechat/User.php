<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models\wechat;

use app\models\Identity;
use app\models\FlagTrait;
use app\models\PageTrait;
use app\components\Html;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Url;

/**
 * 微信业务用户
 *
 * @property int $id
 * @property string $openid openid
 * @property int $flag 用户标识位
 * @property int $perm reversed
 * @property string $is_follow 是否关注
 * @property int $time_join 加入时间
 * @property int $time_subscribe 订阅时间
 * @property int $time_cancel 取关时间
 * @property int $time_active 活跃时间包含发消息、授权登陆
 * @property int $time_active_menu 最后点击菜单时间
 *
 * @property-read bool $allowSendTemplate 是否可以给他发模板消息
 * @property-read bool $allowSendCustomer 是否可以给他发客服消息
 * @property bool $isFollow 是否关注
 * @property-read bool $isBind 是否绑定了微信用户
 * @property-read string $formid 获取或者添加小程序推送用的formid
 * @property-read Identity $identity 通行证信息
 *
 * @author William Chan <root@williamchan.me>
 */
class User extends ActiveRecord
{
    // 还没用到
    const FLAG_OFF_TEMPLATE_NOTIFY = 0x1; // 不接受模板消息
    const FLAG_OFF_CUSTOMER_NOTIFY = 0x2; // 不接受客服消息

    use FlagTrait;
    use PageTrait;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['openid'], 'string', 'max' => 100],
            [['openid'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->time_join = time();
        }
        return parent::beforeSave($insert);
    }

    /**
     * 关联 Identity
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'id'])->inverseOf('user');
    }

    /**
     * 是否关注
     * @return bool
     */
    public function getIsFollow()
    {
        return $this->is_follow === 'Y';
    }

    /**
     * 设置是否关注
     * @return void
     */
    public function setIsFollow($val)
    {
        $this->is_follow = $val ? 'Y' : 'N';
    }

    /**
     * 是否绑定微信用户（0为了兼容一些数据库结构异常情况）
     * @return bool
     */
    public function getIsBind()
    {
        return $this->openid && $this->openid !== '0';
    }

    /**
     * 是否能发送模板消息
     * @return bool
     */
    public function getAllowSendTemplate()
    {
        $wechat = Yii::$app->wechatApp;
        return ($wechat->isWxapp || $this->isFollow) && !empty($this->openid);
    }

    /**
     * 是否能发送客服消息
     * @return bool
     */
    public function getAllowSendCustomer()
    {
        return $this->isFollow && !empty($this->openid) && ($this->time_active > time() - 60 * 60 * 48);
    }

    /**
     * 查询场景用户 fixme: 需要重构
     * @param int $id 用户ID
     * @param string $scenario 场景
     * @return static
     */
    public static function getScenarioUser($id, $scenario = 'A')
    {
        $model = null;
        $wechatApp = Yii::$app->wechatApp;
        $_scenario = $wechatApp->getScenario();
        $wechatApp->setScenario($scenario);
        if (!$id instanceof Identity) {
            $identity = Identity::findOne($id);
        } else {
            $identity = $id;
        }
        if ($identity && $identity->user) {
            $model = $identity->user;
        } elseif ($identity) {
            $model = static::create($identity);
        }
        $wechatApp->setScenario($_scenario);
        return $model;
    }

    /**
     * 载入模型
     * @param int|Identity $id
     * @return static
     */
    public static function loadFor($identity)
    {
        $id = $identity instanceof Identity ? $identity->id : $identity;
        $model = static::findOne(['id' => $id]);
        if ($model === null) {
            return static::create($identity);
        }
        return $model;
    }

    /**
     * 创建新用户
     * @param Identity|int $identity
     * @return static
     */
    public static function create($identity)
    {
        $id = $identity instanceof Identity ? $identity->id : $identity;
        $model = new static([
            'id' => $id,
        ]);
        $model->loadDefaultValues();
        if ($identity instanceof Identity) {
            $model->populateRelation('identity', $identity);
        }
        return $model;
    }

    /**
     * 查找这个用户的formid（小程序推送用）
     * @param bool $autoDelete
     * @return null|string
     */
    public function getFormid($autoDelete = true)
    {
        return WechatFormid::findOneByUser($this->id, $autoDelete);
    }

    /**
     * 标记定义
     * @return array
     */
    public static function flagOptions()
    {
        return [
            // self::FLAG_OFF_TEMPLATE_NOTIFY => '不接受模板消息',
        ];
    }
}
