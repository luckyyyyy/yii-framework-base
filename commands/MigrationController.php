<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\commands;

use app\models\Identity;
use Yii;
use yii\helpers\Json;

/**
 * 迁移工具
 * @author William Chan <root@williamchan.me>
 */
class MigrationController extends Controller
{
    /**
     * 迁移
     */
    public function actionMigration()
    {
        $query = Identity::find();
        foreach ($query->batch(100) as $data) {
            foreach ($data as $model) {
                if ($model->isMigrateAvatar) {
                    Yii::$app->queue3->pushMigrateWechatAvatar($model->id);
                    echo "[$model->id] need to migrate", PHP_EOL;
                }
            }
        }
    }
}
