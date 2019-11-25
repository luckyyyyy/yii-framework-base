<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use app\components\File;
use Yii;
use yii\db\ActiveRecord;

/**
 * app market
 *
 * @property int $id
 * @property string $name 名字
 * @property string $android 下载地址
 * @property string $ios 下载地址
 *
 * @author William Chan <root@williamchan.me>
 */
class AppMarket extends ActiveRecord
{
    use PageTrait;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'appmarket';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'string', 'max' => 120],
            [['ios', 'android'], 'string', 'max' => 250],
            [['name'], 'trim'],

        ];
    }

    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $attributes = ['name', 'ios', 'android'];
        return [self::SCENARIO_DEFAULT => $attributes];
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($name === 'ios' || $name === 'android') {
            if ($value == '0') {
                $value = '';
            }
        }
        parent::__set($name, $value);
    }
}
