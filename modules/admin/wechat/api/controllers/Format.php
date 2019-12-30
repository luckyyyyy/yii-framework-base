<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\modules\admin\wechat\api\controllers;

use app\models\wechat\WechatKeyword;
use app\models\wechat\WechatQrcode;
use app\modules\user\api\controllers\FormatIdentity;
use app\components\Html;
use Yii;
use yii\helpers\Json;

/**
 * 格式化一些通用的信息
 *
 * @author William Chan <root@williamchan.me>
 */
class Format
{

    /**
     * 格式化菜单
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminWechatMenu",
     *     type="array",
     *     @SWG\Items(type="object",
     *         @SWG\Property(property="name", type="string", description="菜单名", example="点我"),
     *         @SWG\Property(property="sub_button", type="array",
     *             @SWG\Items(ref="#/definitions/AdminWechatMenuOne"),
     *         ),
     *     ),
     * )
     */
    public static function menu($data)
    {
        if (!isset($data['menu']['button'])) {
            throw new Exception('ERROR: invalid response data.');
        }
        return $data['menu']['button'];
    }

    /**
     * 格式化关键词
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminWechatKeyword",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="isMatch", type="boolean", description="是否模糊匹配", example=true),
     *     @SWG\Property(property="keyword", type="string", description="关键词", example="差评君"),
     *     @SWG\Property(property="count", type="integer", description="被命中次数", example=5),
     *     @SWG\Property(property="media", ref="#/definitions/AdminWechatMedia"),
     * )
     */
    public static function keyword(WechatKeyword $model)
    {
        return [
            'id' => $model->id,
            'isMatch' => $model->isMatch,
            'keyword' => $model->keyword,
            'count' => $model->count,
            'media' => $model->media,
            'media_id' => explode(',', $model->media_id),
        ];
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminWechatQrcode",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="type", type="boolean", description="是否模糊匹配", example=true),
     *     @SWG\Property(property="imageUrl", type="string", description="关键词", example="差评君"),
     *     @SWG\Property(property="count", type="integer", description="被命中次数", example=5),
     *     @SWG\Property(property="media", ref="#/definitions/AdminWechatMedia"),
     *     @SWG\Property(property="summary", type="string", description="摘要", example="哦哦哦"),
     * )
     *
     */
    public static function qrcode(WechatQrcode $model)
    {
        return [
            'id' => $model->id,
            'type' => $model->type,
            'imageUrl' => $model->imageUrl,
            'expires_in' => $model->time_expire > 0 ? $model->time_expire - time() : -1,
            // 'url' => $model->url,
            'media' => $model->media,
            'media_id' => explode(',', $model->media_id),
            'count' => $model->count,
            'summary' => $model->summary,
        ];
    }

    /**
     * 格式化关键词
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminWechatMedia",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="type", type="integer", description="类型0=文本, 1=图文, 2=图片, 3=语音, 4=音乐, 5=视频, 6=小程序", example=1),
     *     @SWG\Property(property="data", type="object", description="前端提交原始数据", example={"key":"value"}),
     *     @SWG\Property(property="timeModify", type="string", description="最后修改时间", example="5小时前"),
     * )
     */
    public static function media($model)
    {
        return [
            'id' => $model->id,
            'type' => $model->type,
            'data' => $model->data,
            'timeModify' => Html::humanTime($model->time_modify),
        ];
    }

    /**
     * 格式化关键词
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminWechatUser",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="isFollow", type="bool", description="是否关注", example=true),
     *     @SWG\Property(property="timeActive", type="string", description="最后活跃时间", example="5小时前"),
     *     @SWG\Property(property="timeSubscribe", type="string", description="关注时间", example="5小时前"),
     *     @SWG\Property(property="allowSendTemplate", type="bool", description="是否可以发送模板消息", example=true),
     *     @SWG\Property(property="allowSendCustomer", type="bool", description="是否可以发送客服消息", example=true),
     *     @SWG\Property(property="user", ref="#/definitions/UserBasic"),
     * )
     */
    public static function user($model)
    {
        return [
            'id' => $model->id,
            'isFollow' => $model->is_follow === 'Y',
            'timeActive' => Html::humanTime($model->time_active),
            'timeSubscribe' => Html::humanTime($model->time_subscribe),
            'allowSendTemplate' => $model->allowSendTemplate,
            'allowSendCustomer' => $model->allowSendCustomer,
            'user' => FormatIdentity::basic($model->identity),
        ];
    }
}
