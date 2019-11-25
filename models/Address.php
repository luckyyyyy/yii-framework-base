<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * 用户地址
 * 不是做电商/送外卖 暂时不需要设置默认地址
 * 警告：此模型数据结构支持多地址，但是需要修改 loadFor
 *
 * @property int $id
 * @property int $identity_id
 * @property string $name 姓名
 * @property string $phone 手机号码
 * @property string $city 详细地址外的其他信息空格隔开 如国家 省份 城市
 * @property string $address 详细地址
 *
 * @property-read string $full 获取完整的信息 空格隔开 用于快递复制粘贴
 * @author William Chan <root@williamchan.me>
 */
class Address extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'address';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            //[['phone'], 'match', 'pattern' => '/^\+?\d{6,}$/'],
            [['name', 'phone', 'city', 'address'], 'required'],
            [['name', 'phone', 'city', 'address'], 'trim'],
        ];
    }

    /**
     * 关联 通行证
     * @return \yii\db\ActiveQuery
     */
    public function getIdentity()
    {
        return $this->hasOne(Identity::class, ['id' => 'identity_id'])->inverseOf('address');
    }

    /**
     * 获取完整的信息 空格隔开 用于快递复制粘贴
     * @return string
     */
    public function getFull()
    {
        return implode(' ', [
            $this->city,
            $this->address,
            $this->name,
            $this->phone
        ]);
    }

    /**
     * 载入地址模型
     * @param int $identity_id
     * @return static
     */
    public static function loadFor($identity)
    {
        $identity_id = $identity instanceof Identity ? $identity->id : $identity;
        $model = static::findOne(['identity_id' => $identity_id]);
        /* @var $model static */
        if ($model === null) {
            $model = new static([
                'identity_id' => $identity_id,
            ]);
            $model->loadDefaultValues();
        }
        if ($identity instanceof Identity) {
            $model->populateRelation('identity', $identity);
        }
        return $model;
    }

    /**
     * 创建模型
     * @param Identity $identity
     * @return static
     */
    public static function create(Identity $identity)
    {
        $model = new static([
            'identity_id' => $identity->id,
        ]);
        $model->loadDefaultValues();
        $model->populateRelation('identity', $identity);
        return $model;
    }
}
