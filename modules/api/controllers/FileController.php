<?php
/**
 * This file is part of the yii-framework-base.
 * @author Tristana <520tristana@gmail.com>
 */
namespace app\modules\api\controllers;

use app\components\Html;
use app\components\storage\Aliyun;
use app\modules\api\Exception;
use Yii;
use yii\base\DynamicModel;
use yii\filters\AccessControl;
use yii\helpers\ArrayHelper;

/**
 * 默认
 * @author Tristana <520tristana@gmail.com>
 */
class FileController extends Controller
{
    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'POST file/download' => 'download',
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['%SHOP', '%CONSOLE'],
                    ]
                ]
            ]
        ]);
    }

    /**
     * 外链文件下载（先上传到我们的服务器再下载）
     *
     * @return array
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \yii\base\InvalidConfigException
     *
     * @SWG\Definition(
     *     definition="downloadLinks",
     *     type="object",
     *     @SWG\Property(property="links", type="array", description="要下载的文件链接",
     *         @SWG\Items(type="string", description="商品id", example="https://mmbiz.qpic.cn/mmbiz_jpg/yZPTcMGWibvtu6nGabKtupQkSK3Ay8zxaev13KAHgX6jMd1VAichM5SBjicVryheiaI7Ca5oAkvPfslEzqE6416Omw/640?wx_fmt=jpeg&tp=webp&wxfrom=5&wx_lazy=1&wx_co=1")
     *      )
     * )
     * @SWG\Definition(
     *     definition="responseLink",
     *     type="object",
     *     @SWG\Property(property="k", type="string", description="图片原链接", example="https://mmbiz.qpic.cn/mmbiz_jpg/yZPTcMGWibvtu6nGabKtupQkSK3Ay8zxaev13KAHgX6jMd1VAichM5SBjicVryheiaI7Ca5oAkvPfslEzqE6416Omw/640?wx_fmt=jpeg&tp=webp&wxfrom=5&wx_lazy=1&wx_co=1"),
     *     @SWG\Property(property="v", type="string", description="图片转换后链接", example="https://t.g.mg-cdn.com/uploads/attachment/201901/up_5c2f0291a23e00.51413205.jpeg"),
     * )
     *
     *
     * @SWG\Post(path="/file/download",
     *     tags={"Others"},
     *     summary="外链文件下载",
     *     description="上传文件的下载链接，返回文件的访问地址",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="body", name="body", description="提交的数据",
     *         @SWG\Schema(ref="#/definitions/downloadLinks")
     *     ),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(type="array",
     *              @SWG\Items(ref="#/definitions/responseLink")
     *         )
     *     ),
     *     @SWG\Response(response=400, description="文件下载出错")
     * )
     */
    /**
     */
    public function actionDownload()
    {
        $links = Yii::$app->request->getBodyParam('links');
        $dynamicModel = DynamicModel::validateData(['links' => $links], [
          ['links', 'required'],
          ['links', 'app\modules\shop\validators\ArrayValidator'],
        ]);
        if (!$dynamicModel->validate()) {
            $errors = $dynamicModel->getFirstErrors();
            throw new Exception($errors, '请求参数错误：' . reset($errors));
        }
        $result = [];
        // uploaded file
        try {
            foreach ($links as $k => $link) {
                $result[$k] = [
                    'k' => $link,
                ];
                $arr = parse_url($link);
                if ($arr === false) {
                    throw new Exception(null, '链接 ' . $link . ' 是无效的');
                }
                $ext = uniqid('up_', true);
                if (isset($arr['query'])) {
                    parse_str($arr['query'], $query);
                    if (isset($query['wx_fmt'])) {
                        $ext .= '.' . $query['wx_fmt'];
                    }
                }
                $time = date('Ym');
                $type = 'temp';
                $fullPath = sprintf('/uploads/static/%s/%d', $type, $time) . $ext;
                /** @var Aliyun $storage */
                $storage = Yii::$app->storage;
                if (!$storage->putObject($fullPath, $link)) {
                    throw new Exception(null, "文件上传失败：" . $storage->getLastError());
                } else {
                    $result[$k]['v'] = Html::extUrl($fullPath);
                }
            }
        } catch (\Exception $e) {
            throw new Exception(null, $e->getMessage(), $e->getCode());
        }
        return $result;
    }
}
