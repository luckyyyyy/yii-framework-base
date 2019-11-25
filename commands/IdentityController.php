<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use app\components\Chinese;
use app\components\File;
use app\models\Identity;
use Yii;

/**
 * 通行证操作
 * @author William Chan <root@williamchan.me>
 */
class IdentityController extends Controller
{
    /**
     * 这是例子 - 导出前10个用户
     */
    public function actionShow()
    {
        echo '用户信息 ...', PHP_EOL, PHP_EOL;
        $models = Identity::find()->limit(10)->all();
        echo 'Identity', PHP_EOL;
        echo '+----+------------+-------------+', PHP_EOL;
        echo '| id | User name  | phone       |', PHP_EOL;
        echo '+----+------------+-------------+', PHP_EOL;
        // utf8的中文 2-4 mb4的表情也是 2-4 对 %-x兼容不太好
        // 所以可以强制转gbk，这样直接全认成2算了
        foreach ($models as $model) {
            echo mb_convert_encoding(sprintf(
                '| %-2s | %-10s | %-11d |' . PHP_EOL,
                $model->id,
                mb_convert_encoding($model->name, 'gbk', 'utf-8'),
                $model->phone
            ), 'utf-8', 'gbk');
        }
        echo '+----+------------+-------------+', PHP_EOL;
    }


    /**
     * 修改任意用户的密码
     */
    public function actionPassword($id, $password)
    {
        $identity = Identity::findOne($id);
        if ($identity) {
            $identity->password = $password;
            $identity->save();
        }
        echo '用户#' . $id . '密码修改为：' . $password, PHP_EOL;
    }

    /**
     * 创建通行证
     * @param int $phone  手机号
     * @param int $name   昵称
     * @param int $avatar 头像
     * @param int $gender 性别
     */
    public function actionCreate($phone, $name, $avatar, $gender, $password = null)
    {
        $password = $password ?? '@#' . rand(1000000, 9999999);
        $params = [
            'phone' => $phone,
            'name' => $name,
            'avatar' => '',
            'gender' => $gender,
            'flag' => Identity::FLAG_MOCK,
            'password' => $password,
        ];
        $identity = new Identity();
        $identity->setScenario(Identity::SCENARIO_ADMIN);
        $identity->attributes = $params;
        if ($identity->save(false)) {
            echo "identity: $name created, id=$identity->id, phone=$phone, password=$password", PHP_EOL;
            $path = File::createFileSavePath(File::TYPE_AVATAR, $identity->id) . '/' . File::createFileName();
            Yii::$app->storage->putObject($path, $avatar, $Header = null);
            $identity->avatar = $path;
            if ($identity->save(false)) {
                echo 'identity avatar set successful.', PHP_EOL;
            }
        }
    }
}
