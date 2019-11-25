<?php
/**
 * This file is part of the yii-framework-base.
 * @author fangjiali
 */

namespace app\modules\api\controllers;

use app\components\Html;
use app\models\Feedback;
use app\models\Identity;
use app\modules\api\Exception;
use Yii;

/**
 * Feed Back
 *
 * @author fangjiali
 * @SWG\Tag(name="Feed - Back", description="反馈")
 */
class FeedBackController extends Controller
{
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST feedback' => 'create',
        ];
    }

    /**
     * 提交反馈
     *
     * @SWG\Definition(
     *     definition="feedBackCreate",
     *     type="object",
     *             @SWG\Property(property="type", type="string", description="反馈类型", example="1"),
     *             @SWG\Property(property="content", type="string",  description="反馈内容", example="内容"),
     *             @SWG\Property(property="attachment", type="attachment",  description="图片 附件ID 或 wechat://{wechat_alias}/{media_id}",example={1,2,3}),
     * )
     *
     * @SWG\Post(path="/feedback",
     *     tags={"Feed - Back"},
     *     description="提交反馈",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据", required=true,
     *         @SWG\Schema(ref="#/definitions/feedBackCreate")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/FeedBack")
     *     )
     * )
     */
    public function actionCreate()
    {
        $params = Yii::$app->request->getBodyParams();
        if (isset($params['attachment']) && count($params['attachment']) > 3) {
            throw new Exception("图片数量不能超过3张");
        }

        $identity = Yii::$app->user->identity;
        $params['identity_id'] = $identity instanceof Identity ? $identity->id : $identity;

        $model = new Feedback();
        $model->attributes = $params;
        try {
            if (!$model->save()) {
                throw new Exception($model->getFirstErrors(), '操作失败');
            } else {
                return $this->Format($model);
            }
        } catch (\yii\db\Exception $e) {
            throw new Exception($e->getMessage(), '操作失败 ' . $e->getName());
        }
    }

    /**
     * 格式化输出
     * @return array
     *
     * @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/FeedBack")
     *     )
     */
    private function Format(Feedback $model)
    {
        $result = [
            'id' => $model->id,
            'type' => $model->type,
            'isDeal' => $model->isDeal,
            'content' => $model->content,
            'attachment' => Html::extUrl($model->attachment),
            'time_create' => date('Y-m-d H:i', $model->time_create)
        ];

        return $result;
    }
}
