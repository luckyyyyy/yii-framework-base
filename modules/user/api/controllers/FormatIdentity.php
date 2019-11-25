<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\user\api\controllers;

use app\components\Html;
use app\models\Identity;
use app\models\wechat\User;
use app\models\Address;
use app\models\Admin;
use app\models\Block;
use Yii;
use yii\helpers\Url;

/**
 * 格式化用户帐号
 *
 * @author William Chan <root@williamchan.me>
 */
class FormatIdentity
{

    /**
     * 缓存是否是管理员信息
     * @var bool
     */
    private static $_isAdmin;

    /**
     * 判断当前用户是否是管理员
     * @return bool
     */
    private static function getIsAdmin()
    {
        $user = Yii::$app->user;
        if (static::$_isAdmin !== null) {
            return static::$_isAdmin;
        } else {
            static::$_isAdmin = $user->isAdmin('%');
            return static::$_isAdmin;
        }
    }

    /**
     * 判断是否是当前用户
     * @return bool
     */
    private static function getIsMine($identity)
    {
        $user = Yii::$app->user;
        return $identity->id === $user->id;
    }

    /**
     * 用户基础信息
     * @param Identity $identity
     * @return array
     *
     * @SWG\Definition(
     *     definition="UserBasic",
     *     @SWG\Property(property="avatar", type="string", description="头像地址", example="/path/to"),
     *     @SWG\Property(property="name", type="string", description="名字", example="名字"),
     *     @SWG\Property(property="gender", type="integer", description="性别 0=未知 1=男 2=女", example=0),
     *     @SWG\Property(property="timeActive", type="integer", description="最后登录时间", example=0),
     *     @SWG\Property(property="timeJoin", type="integer", description="注册时间", example=0),
     *     @SWG\Property(property="phone", type="string", description="手机号", example="1385800xxxx"),
     *     @SWG\Property(property="point", type="integer", description="积分", example=100),
     *     @SWG\Property(property="isBindPhone", type="bool", description="是否绑定手机号", example=true),
     *     @SWG\Property(property="isBindWechat", type="bool", description="是否绑定微信号", example=true),
     *     @SWG\Property(property="__referral", type="string", description="自己的 referral ID，地址栏加上 ?__from_uid=__referral 就可以成为别人的推介", example="2a35d"),
     *     @SWG\Property(property="__from_referral", type="integer", description="这次会话的 referral ID 是谁（自己可见）", example="1a033"),
     * )
     */
    public static function basic($identity)
    {
        $user = Yii::$app->user;
        /* @var $user \app\components\User */
        $item = [
            'name' => $identity->name,
            'gender' => $identity->gender,
            'id' => $identity->id
        ];
        $avatar = Html::extUrl($identity->avatar);
        $item['avatar'] = $avatar;

        // 自己或者当前用户是管理员 才有权限查看下面的信息
        if (static::getIsMine($identity) || static::getIsAdmin()) {
            // 其他信息
            $item['timeActive'] = Html::humanTime($identity->time_active, 'Y-m-d H:i:s');
            $item['phone'] = $identity->phone;
            $item['point'] = $identity->point;
            $item['isBindPhone'] = $identity->isBindPhone;
            $item['isBindWechat'] = $identity->isBindWechat;
            $item['timeJoin'] = Html::humanTime($identity->time_join, 'Y-m-d');
            // 招募ID 36进制
            $item['__referral'] = base_convert($identity->id, 10, 36);

            // 自己被谁推荐
            if (static::getIsMine($identity)) {
                $item['__from_referral'] = $user->referral;
            }
            // 其他选项
            // $item['constellationLabel'] = $identity->constellation > 0 ? $identity->constellationLabel : '未知星座';

            // 当前用户是超级管理员 DEBUG信息
            if ($user->isSuper) {
                $debugs = [];
                $debugs['id'] = $identity->id;
                $debugs['point'] = $identity->point;
                $debugs['days'] = intval((time() - $identity->time_join) / 86400);
                if ($user->isRoot) {
                    $debugs['ip'] = $identity->ip;
                    $debugs['phone'] = $identity->phone;
                    $debugs['unionid'] = $identity->unionid;
                    $debugs['agent'] = $identity->agent;
                }
                $item['debug'] = $debugs;
            }
        }
        return $item;
    }

    /**
     * @param Identity $identity
     * @return array
     *
     * @SWG\Definition(
     *     definition="UserFull",
     *     type="object",
     *     allOf={
     *        @SWG\Schema(ref="#/definitions/UserBasic"),
     *        @SWG\Schema(ref="#/definitions/UserPerms"),
     *        @SWG\Schema(ref="#/definitions/UserAddress"),
     *        @SWG\Schema(ref="#/definitions/UserBlock"),
     *     }
     * )
     */
    public static function full(Identity $identity)
    {
        // 用户地址信息
        return array_merge(
            static::basic($identity),
            static::perms($identity),
            static::address($identity),
            static::block($identity)
        );
    }

    /**
     * 获取管理员信息
     * @param Identity $identity
     * @return array
     *
     * @SWG\Definition(
     *     definition="UserPerms",
     *     type="object",
     *     @SWG\Property(property="perms", type="object",
     *         @SWG\Property(property="summary", type="string", description="称谓", example="root"),
     *         @SWG\Property(property="flags", description="黑名单flags", ref="#/definitions/Flags"),
     *         @SWG\Property(property="isRoot", type="boolean", description="是否是root用户", example=true),
     *         @SWG\Property(property="isSuper", type="boolean", description="是否是超级管理员用户", example=true),
     *         @SWG\Property(property="isAdmin", type="boolean", description="是否是管理员用户", example=true),
     *     ),
     * )
     */
    public static function perms($identity)
    {
        $item = [];
        if ($identity->admin || static::getIsAdmin()) {
            $perms = [];
            $admin = $identity->admin ? $identity->admin : Admin::create($identity);
            if ($identity->isRoot) {
                $perms['summary'] = 'root';
            } else {
                $perms['summary'] = $admin->summary;
            }
            $perms['flags'] = $admin->flags;
            $perms['isRoot'] = $identity->isRoot;
            $perms['isSuper'] = $identity->canAdmin('*');
            $perms['isAdmin'] = $identity->canAdmin('%');
            $item['perms'] = $perms;
        }
        return $item;
    }

    /**
     * 获取黑名单
     * @param Identity $identity
     * @return array
     *
     * @SWG\Definition(
     *     definition="UserBlock",
     *     type="object",
     *     @SWG\Property(property="block", type="object",
     *         @SWG\Property(property="flags", description="黑名单flags", ref="#/definitions/Flags"),
     *         @SWG\Property(property="isGlobal", type="boolean", description="全局黑名单", example=true),
     *     ),
     * )
     */
    public static function block($identity)
    {
        $item = [];
        if (static::getIsAdmin()) {
            $block = $identity->block ? $identity->block : Block::create($identity);
            $item['block'] = [
                'flags' => $block->flags,
                'isGlobal' => $block->isGlobal,
            ];
        }
        return $item;
    }

    /**
     * 用户地址信息
     * @param Identity $identity
     * @return array
     *
     * @SWG\Definition(
     *     definition="UserAddress",
     *     @SWG\Property(property="address", type="object",
     *         @SWG\Property(property="city", type="string", description="城市省份", example="浙江 杭州"),
     *         @SWG\Property(property="name", type="string", description="姓名", example="陈老板"),
     *         @SWG\Property(property="address", type="string", description="详细地址", example="阿里巴巴西溪园区 4号楼 报告厅"),
     *         @SWG\Property(property="phone", type="string", description="电话号码", example="13800000000"),
     *     ),
     * )
     */
    public static function address($identity)
    {
        $item = [];
        if (static::getIsMine($identity) || static::getIsAdmin()) {
            // 地址
            $address = $identity->address ? $identity->address : Address::create($identity);
            $item['address'] = [
                'city' => $address->city,
                'name' => $address->name,
                'phone' => $address->phone,
                'address' => $address->address,
                'full' => $address->full
            ];
        }
        return $item;
    }
}
