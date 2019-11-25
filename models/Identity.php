<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\models\wechat\User;
use app\components\Html;
use app\components\File;
use Yii;
use yii\base\NotSupportedException;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\helpers\Json;
use yii\web\IdentityInterface;
use yii\base\Exception;

/**
 * 用户通行证
 *
 * @property int $id
 * @property string $unionid wechat unionid
 * @property string $name 用户昵称
 * @property string $password
 * @property int $flag 用户标识位
 * @property int $time_join 加入时间
 * @property int $time_active 最后登录时间（产生授权请求）
 * @property int $time_deny 整体屏蔽时间（黑名单）
 * @property float $location_lat 位置纬度
 * @property float $location_lon 位置经度
 * @property string $agent 最后访问客户端
 * @property string $ip IP地址
 * @property array $states 登录状态数据，通常是微信上的用户信息
 * @property int $point 积分点数
 * @property string $phone 手机号
 * @property string $avatar 头像
 * @property int $gender 真实性别（0=未填写/1=男/2=女）
 * @property int $constellation 星座（0=未填写/1=白羊.../12=双鱼）
 * @property string $city 城市，与省份之间用空格分开
 *
 * @property-read bool $isBindPhone 是否绑定手机号
 * @property-read bool $isBindWechat 是否绑定微信
 * @property-read bool $isMigrateAvatar 是否需要迁移头像到阿里云
 * @property-read Token $token 关联的 AccessToken
 * @property-read User $user 场景用户
 * @property-read Admin $admin 管理员信息
 * @property-read Block $block 黑名单
 * @property-read Address $address 用户地址信息
 * @property-read IdentityLimit $limit 限制信息
 * @property-read bool $isDead 是否已注销
 * @property-read string $genderLabel 性别
 * @property-read string $constellationLabel 星座
 * @property-read bool $isRoot 是否为顶级管理
 *
 * @author William Chan <root@williamchan.me>
 */
class Identity extends ActiveRecord implements IdentityInterface
{
    use FlagTrait;
    use PageTrait;

    const ID_ROOT = 1;
    const FLAG_MOCK = 0x10; // 手动创建的账户
    const FLAG_DEAD = 0x100; // 账号注销

    const SCENARIO_ADMIN = 'Admin';
    const SCENARIO_REGISTER = 'register';
    const SCENARIO_RESET = 'reset';

    private $_limit;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'identity';
    }

    /**
     * 自动将 states 转换为数组
     * @inheritdoc
     */
    public function __get($name)
    {
        if ($name === 'states') {
            $value = $this->getAttribute($name);
            if (!is_array($value)) {
                $this->setAttribute($name, $value = Json::decode($value));
            }
            return $value;
        } elseif ($name === 'avatar') {
            if (!$this->getAttribute($name)) {
                return '/avatar/default.png';
            }
        }
        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($name === 'password') {
            $value = Yii::$app->security->generatePasswordHash($value, 10);
        } elseif ($name === 'phone' && $value === '') {
            $value = null;
        }
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        // 中文范围
        $chars = '\x{3400}-\x{a4ff}\x{f900}-\x{faff}a-zA-Z';
        return [
            [['unionid', 'name', 'city'], 'string', 'max' => 100],
            [['location_lat', 'location_lon'], 'number'],
            [['agent'], 'string', 'max' => 255],
            [['unionid', 'phone'], 'unique'],
            [['avatar', 'name'], 'required'],
            // [['name'], 'validateName', 'on' => [self::SCENARIO_DEFAULT]],
            [['avatar'], 'validateAvatar'],
            // 名字只能包含常规中文、英文字符和数字下划线，2-13个字符内
            // 且不能以数字开头。
            // [['name'], 'match',
            //     'pattern' => '/^[' . $chars . '][' . $chars . '0-9_]{1,11}$/u',
            //     'message' => '名字只能包含2-12个字符，支持中英文、数字和"_"，且不能以数字开头。',
            //     'on' => [self::SCENARIO_DEFAULT]
            // ],
            // ['name', 'unique', 'message' => '名字已经被占用了'],
            [['phone'], 'match', 'pattern' => '/^\+?\d{6,}$/'],
            [['gender'], 'in', 'range' => array_keys(static::genderLabels())],
            [['constellation'], 'in', 'range' => array_keys(static::constellationLabels())],
        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        return [
            self::SCENARIO_DEFAULT => ['name', 'avatar', 'gender'],
            self::SCENARIO_ADMIN => [
                'name', 'avatar', 'password', 'point', 'gender', 'phone', 'flag'
            ],
            self::SCENARIO_REGISTER => [
                'name', 'password', 'gender', 'phone'
            ],
            self::SCENARIO_RESET => [
                'password'
            ],
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
        $value = $this->getAttribute('states');
        if (is_array($value)) {
            $this->setAttribute('states', Json::encode($value));
        }
        return parent::beforeSave($insert);
    }

    /**
     * Validates password
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        // no empty password is allowed.
        if (empty($this->password)) {
            return false;
        }
        return Yii::$app->security->validatePassword($password, $this->password);
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        throw new \yii\base\NotSupportedException('通行证不应该被删除');
        // @fixme 理论上不应该会有删除的情况，其它数据都没有同步删除。
        parent::afterDelete();
        Token::deleteAll(['user_id' => $this->id]);
    }

    /**
     * 关联 Token
     * @return \yii\db\ActiveQuery
     */
    public function getToken()
    {
        return $this->hasOne(Token::class, ['user_id' => 'id'])->limit(1)->inverseOf('identity');
    }

    /**
     * 关联的用户
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'id'])->inverseOf('identity');
    }

    /**
     * 关联的管理
     * @return \yii\db\ActiveQuery
     */
    public function getAdmin()
    {
        return $this->hasOne(Admin::class, ['id' => 'id'])->inverseOf('identity');
    }

    /**
     * 黑名单
     * @return \yii\db\ActiveQuery
     */
    public function getBlock()
    {
        return $this->hasOne(Block::class, ['id' => 'id'])->inverseOf('identity');
    }

    /**
     * 关联地址
     * @return \yii\db\ActiveQuery
     */
    public function getAddress()
    {
        return $this->hasOne(Address::class, ['identity_id' => 'id'])->inverseOf('identity');
    }

    /**
     * 关联限制模型
     * @return IdentityLimit 限制
     */
    public function getLimit()
    {
        if ($this->_limit === null) {
            $this->_limit = IdentityLimit::loadFor($this);
        }
        return $this->_limit;
    }

    /**
     * 校验名字禁止违禁字
     * @return void
     */
    public function validateName()
    {
        if ($this->isAttributeChanged('name')) {
            if (strpos($this->name, '客服') !== false ||
                strpos($this->name, '管理') !== false ||
                strpos($this->name, 'root') !== false
            ) {
                $this->addError('name', '名字包含禁用字词');
            } elseif (mb_substr($this->name, 0, 2) === '所有') {
                $this->addError('name', '名字不可以是所有开头');
            } elseif (Html::isSpam($this->name)) {
                $this->addError('name', '名字包含禁用字词');
            }
        }
    }

    /**
     * 效验头像
     * @return void
     */
    public function validateAvatar()
    {
        if ($this->isAttributeChanged('avatar')) {
            // 设置头像只允许是数字或者 微信头像 其他情况都是原始地址
            if (File::checkCondition($this->avatar)) {
                // 上传头像不使用队列
                try {
                    $this->avatar = File::findOne($this->avatar, File::TYPE_AVATAR, false);
                } catch (Exception $e) {
                    $this->addError('avatar', '用户头像设置失败');
                }
            } elseif (strstr($this->avatar, '.qlogo.cn') !== false) {
                $this->avatar = str_replace('http://', 'https://', $this->avatar);
            } else {
                $this->avatar = $this->getOldAttribute('avatar');
            }
        }
    }

    /**
     * @return bool 是否填写了手机号
     */
    public function getIsBindPhone()
    {
        return !empty($this->phone);
    }

    /**
     * @return bool 是否绑定微信号
     */
    public function getIsBindWechat()
    {
        return !empty($this->unionid);
    }


    /**
     * @return bool 是否已注销
     */
    public function getIsDead()
    {
        return $this->hasFlag(self::FLAG_DEAD);
    }

    /**
     * 获取屏蔽时长 其中设置为（1）表示永远封禁，恶劣用户，返回 HTTP （444）
     * 444 Connection Closed Without Response.
     * A non-standard status code used to instruct nginx to close the connection without sending a response to the client,
     * most commonly used to deny malicious or malformed requests.
     * By Nginx
     * @return int
     */
    public function getBlockDuration()
    {
        if ($this->time_deny === 1) {
            return -1;
        }
        $now = time();
        return $now >= $this->time_deny ? 0 : $this->time_deny - $now;
    }

    /**
     * @return string 性别描述
     */
    public function getGenderLabel()
    {
        $labels = static::genderLabels();
        return $labels[$this->gender] ?? '';
    }

    /**
     * @return string 星座描述
     */
    public function getConstellationLabel()
    {
        $labels = static::constellationLabels();
        return $labels[$this->constellation] ?? '';
    }

    /**
     * @return bool 是否为顶级管理
     */
    public function getIsRoot()
    {
        return $this->id === self::ID_ROOT;
    }

    /**
     * 注销帐号（但保留数据）
     */
    public function kill()
    {
        $this->updateAttributes([
            'unionid' => null,
            'phone' => new Expression('CONCAT(id, \'_\', phone)'),
        ]);
    }

    /**
     * 检查管理权限
     * @param string $module 管理模块，如：secret, wiki ...
     * @return bool
     */
    public function canAdmin($module = null)
    {
        if ($this->getIsRoot()) {
            return true;
        }
        $admin = $this->admin;
        if ($admin === null) {
            return false;
        } elseif ($module === '*') {
            return $admin->getIsSuper();
        } elseif ($module === '%') {
            // @fixme 别滥用 这只判断有没有任意管理员权限
            return $admin->isAdmin();
        } else {
            return $admin->canAdmin($module);
        }
    }

    /**
     * 检查黑名单
     * @param string
     * @return bool
     */
    public function canBlocked($module = null)
    {
        // @fixme root 也可加入黑名单 哈哈
        // if ($this->getIsRoot()) {
        //     return false;
        // }
        $block = $this->block;
        if ($block === null) {
            return false;
        } else {
            return $block->canBlocked($module);
        }
    }

    /**
     * 设置微信数据到 attributes
     * @param array $states
     * @return void
     */
    public function setWechatAttributes($states)
    {
        // 有用的是这些 但是不需要全部设置
        // "unionid" ,"openid", ,"nickname" ,"sex" ,"language" ,"city" ,"province" ,"country" ,"headimgurl"
        /** @fixme 小程序是 avatarurl */
        $this->gender = $states['sex'] ?? $states['gender'] ?? 0;
        $this->city = $states['province'] . ' ' . $states['country'] . ' ' . $states['city'];
        $this->avatar = $states['headimgurl'] ?? $states['avatarurl'] ?? null;
        $this->name = $states['nickname'];
        $this->states = $states;
    }

    /**
     * 是否需要迁移头像
     * @return bool
     */
    public function getIsMigrateAvatar()
    {
        return empty($this->avatar) || strstr($this->avatar, '.qlogo.cn') !== false;
    }

    /**
     * 迁移头像到阿里云固定连接
     * @param int $id
     * @return bool
     */
    public static function migrateAvatar($id)
    {
        $identity = static::findOne($id);
        if ($identity && $identity->isMigrateAvatar) {
            $avatar = $identity->avatar;
            // 微信直接拉原图
            if (substr($avatar, -4) === '/132') {
                $avatar = substr($avatar, 0, strlen($avatar) -4) . '/0';
            }
            $storage = Yii::$app->storage;
            $path = File::createFileSavePath(File::TYPE_AVATAR, $identity->id) . '/' . File::createFileName();
            if ($storage->putObject($path, $avatar)) {
                $identity->avatar = $path;
                $identity->save(false);
            }
            return true;
        }
        return false;
    }

    /**
     * @return array 性别选项
     */
    public static function genderLabels()
    {
        return ['未填写', '男', '女'];
    }

    /**
     * @return array 星座列表
     */
    public static function constellationLabels()
    {
        return [
            '未填写',
            '白羊座', '金牛座', '双子座', '巨蟹座',
            '狮子座', '处女座', '天秤座', '天蝎座',
            '射手座', '摩羯座', '水瓶座', '双鱼座',
        ];
    }

    /**
     * 标记定义
     * @return array
     */
    public static function flagOptions()
    {
        return [
            self::FLAG_DEAD => '账号注销',
        ];
    }

    /* --- implements IdentityInterface --- */

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        $model = static::findOne(['id' => $id]);
        return $model && !$model->isDead ? $model : null;
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        $token = Token::findOne(['id' => $token]);
        return $token && $token->identity && !$token->identity->isDead ? $token->identity : null;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        throw new NotSupportedException('"' . __METHOD__ . '" is not implemented.');
        //return md5($this->time_join . '@' . $this->id . '@' . $this->time_join)
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        throw new NotSupportedException('"' . __METHOD__ . '" is not implemented.');
        //return $this->getAuthKey() === $authKey;
    }
}
