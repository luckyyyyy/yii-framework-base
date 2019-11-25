<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\modules\api\controllers;

use app\components\Html;
use app\models\Advertise;
use Yii;

/**
 * 格式化一些通用的信息
 *
 * @author William Chan <root@williamchan.me>
 */
class Format
{

    /**
     * 格式化输出 app Market
     * @return array
     *
     * @SWG\Definition(
     *     definition="AdminMarket",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="name", type="string", description="APP名字", example="ofo单车"),
     *     @SWG\Property(property="android", type="string", description="安卓下载地址 可能为空", example="/path/to"),
     *     @SWG\Property(property="ios", type="string", description="IOS下载地址 可能为空", example="/path/to"),
     * )
     */
    public static function appMarket($model)
    {
        return [
            'id' => $model->id,
            'ios' => Html::extUrl($model->ios),
            'android' => Html::extUrl($model->android),
            'name' => $model->name,
        ];
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Definition(
     *     definition="attachmentOne",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="type", type="string", description="分类", example="app"),
     *     @SWG\Property(property="name", type="string", description="文件名", example="图片.jpg"),
     *     @SWG\Property(property="size", type="integer", description="大小", example=24758),
     *     @SWG\Property(property="url", type="string", description="访问地址", example="http://example.com/aaa.jpg"),
     *     @SWG\Property(property="mime", type="string", description="mime", example="image/png")
     * )
     */
    public static function attachmentOne($item)
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'name' => $item->name,
            'size' => $item->size,
            'url' => Html::extUrl($item->path),
            'mime' => $item->mime,
        ];
    }

    /**
     * 广告
     * @param Advertise $item
     * @return array
     *
     * @SWG\Definition(
     *     definition="advertiseBasic",
     *     type="object",
     *     @SWG\Property(property="id", type="integer", description="id", example=1),
     *     @SWG\Property(property="name", type="string", description="广告名称", example="小米"),
     *     @SWG\Property(property="time", type="integer", description="时间戳", example="1534838400"),
     *     @SWG\Property(property="time_format", type="string", description="时间格式化", example="2018-10-12"),
     *     @SWG\Property(property="author", type="string", description="作者", example="拉犁"),
     *     @SWG\Property(property="category", type="string", description="分类", example="first"),
     * )
     */
    public static function advertiseBasic(Advertise $item)
    {
        $result = [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category,
            'time' => $item->time,
            'time_format' => date('Y-n-j', $item->time),
            'author' => $item->author,
        ];

        return $result;
    }
}
