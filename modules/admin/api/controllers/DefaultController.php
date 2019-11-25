<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\modules\admin\api\controllers;

use app\models\Attachment;
use app\models\Admin;
use app\modules\api\Exception;
use app\modules\api\MetaResponse;
use app\modules\api\controllers\Format;
use Yii;
use yii\web\NotFoundHttpException;
use yii\filters\AccessControl;

/**
 * admin
 *
 * @author William Chan <root@williamchan.me>
 * @SWG\Tag(name="Admin", description="管理员 - 杂项")
 */
class DefaultController extends \app\modules\api\controllers\DefaultController
{

    /**
     * @var array 扩展名
     */
    protected $extensions = [];

    /**
     * @var int 文件最大尺寸
     */
    protected $maxSize = 200 * 1048576;

    /**
     * @var string 类型 决定文件上传路径
     */
    protected $type;

    /**
     * @inheritdoc
     */
    public static function urlRules()
    {
        return [
            'GET access' => 'access',
            'POST attachment/auth' => 'upload-auth',
            'POST attachment/upload' => 'upload-file',
            'GET attachment/<type:\w+>' => 'get-attachment',
            'DELETE attachment/<id:\d+>' => 'del-attachment',
        ];
    }

    /**
     * @inheritdoc
     * # -> root, * -> super admin,
     * % -> module admin, @@ -> registered user
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = [
            'class' => AccessControl::class,
            'rules' => [[
                'allow' => true,
                'roles' => ['%%'],
            ]],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    protected function bearerConfig()
    {
        return [];
    }

    /**
     * 管理用户操作权限
     * @return array
     *
     * @SWG\Get(path="/admin/access",
     *     tags={"Admin"},
     *     description="当前用户的操作范围",
     *     produces={"application/json"},
     *     security={{"api_key": {}}},
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="object",
     *             @SWG\Property(property="MENU_WECHAT", type="boolean", description="微信管理", example=false),
     *             @SWG\Property(property="MENU_TESTING", type="boolean", description="管理众测", example=false),
     *             @SWG\Property(property="MENU_APP_MARKER", type="boolean", description="管理APP推荐", example=false),
     *             @SWG\Property(property="MENU_MANAGER", type="boolean", description="管理管理员", example=false),
     *             @SWG\Property(property="MENU_LOG", type="boolean", description="查看日志", example=false),
     *             @SWG\Property(property="MENU_CMS", type="boolean", description="内容管理系统", example=false),
     *         )
     *     ),
     *     @SWG\Response(response=404, description="众测不存在"),
     *     @SWG\Response(response=400, description="其他情况"),
     * )
     */
    public function actionAccess()
    {
        $user = Yii::$app->user;
        $result = [
            'MENU_WECHAT' => $user->isAdmin(Admin::FLAG_WECHAT), // 微信管理
            'MENU_TESTING' => $user->isAdmin(Admin::FLAG_TESTING), // 众测管理
            'MENU_APP_MARKER' => $user->isAdmin(Admin::FLAG_APP_MARKER), // app 推荐管理
            'MENU_CRON' => $user->isSuper, // 定时任务
            'MENU_MANAGER' => $user->isSuper, // 管理员列表
            'MENU_BLOCK' => $user->isAdmin('%'), // 黑名单列表
            'MENU_IDENTITY' => $user->isAdmin('%'), // 通行证列表
            'MENU_LOG' => $user->isRoot, // 日志
            'MENU_SHORT_URL' => $user->isAdmin('%'), // 短链接
            'MENU_FUN_CODE' => $user->isSuper, // 激活码
            'MENU_FEED_BACK' => $user->isAdmin('%'), // 用户反馈
            'MENU_WEBSITE' => $user->isAdmin(Admin::FLAG_WEBSITE), // 官网管理
            'MENU_MP' => $user->isAdmin(Admin::FLAG_WECHAT), // 文章阅读量
            'MENU_SHOP' => $user->isAdmin(Admin::FLAG_SHOP) || $user->isAdmin(Admin::FLAG_CONSOLE) || $user->isAdmin(Admin::FLAG_FINANCE) || $user->isAdmin(Admin::FLAG_CUSTOMER), // 商城管理
            'MENU_ADVERTISE' => $user->isAdmin(Admin::FLAG_ADVERTISE), // 广告管理
            'SUB_MENU' => $this->actionGetSubMenu(),
        ];
        return $result;
    }

    /**
     * 文件上传
     * @return string
     * @throws \yii\web\HttpException
     *
     * @SWG\Post(path="/admin/attachment/upload",
     *     tags={"Admin"},
     *     summary="上传附件",
     *     description="通过 multipart/form-data 上传文件，返回文件名及网址",
     *     produces={"multipart/form-data"},
     *     security={{"api_key": {}}},
     *     @SWG\Parameter(in="formData", name="file", type="file", description="上传文件实例，multipart/form-data 表单数据", required=true),
     *     @SWG\Parameter(in="formData", name="type", type="integer", description="管理员选择上传文件的分类，管理员可以自定义分类，影响附件表。", default="0"),
     *     @SWG\Parameter(in="formData", name="simditor", type="integer", enum={0, 1}, description="是否为 Simditor 兼容模式", default=0),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(ref="#/definitions/attachmentOne")
     *     ),
     *     @SWG\Response(response=400, description="文件上传出错")
     * )
     */
    public function actionUploadFile()
    {
        $req = Yii::$app->request;
        $this->type = $req->post('type');
        return parent::actionUploadFile();
    }

    /**
     * 获取附件列表
     * @return array
     *
     * @SWG\Get(path="/admin/attachment/{type}",
     *     tags={"Admin"},
     *     description="获取相册（附件）列表",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="type", type="string", description="相册分类 可以是字符串", required=true, default="0"),
     *     @SWG\Parameter(ref="#/parameters/offsetPageParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetPointParam"),
     *     @SWG\Parameter(ref="#/parameters/offsetLimitParam"),
     *     @SWG\Response(response=200, description="success",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/attachmentOne")
     *         )
     *     ),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionGetAttachment($type)
    {
        $all = Attachment::find()->where(['type' => $type])->findByOffset();
        $result = [];
        foreach ($all['result'] as $item) {
            $result[] = Format::attachmentOne($item);
        }
        return new MetaResponse($result, ['extra' => $all['extra']]);
    }

    /**
     * 删除一个附件
     * @return array
     *
     * @SWG\Delete(path="/admin/attachment/{id}",
     *     tags={"Admin"},
     *     description="删除一个附件 不影响已使用的附件",
     *     security={{"api_key": {}}},
     *     produces={"application/json"},
     *     @SWG\Parameter(in="path", name="id", type="string", description="附件id", required=true, default="0"),
     *     @SWG\Response(response=200, description="success"),
     *     @SWG\Response(response=401, description="未授权")
     * )
     */
    public function actionDelAttachment($id)
    {
        $model = Attachment::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('删除失败，附件不存在。');
        }
        return $model->delete();
    }

    /**
     * 获取二级记录
     *
     * @return array
     */
    private function actionGetSubMenu()
    {
        $user = Yii::$app->user;
        return [
            'MENU_SHOP' => [
                'home' => true,
                'product-manage' => true,
                'order-manage' => true,
                'aftermarket' => true,
                'comment' => true,
                'promotion/home' => true,
                'distribution/home' => $user->isAdmin(Admin::FLAG_SHOP) || $user->isAdmin(Admin::FLAG_CONSOLE) || $user->isAdmin(Admin::FLAG_FINANCE),
                'debug' => true,
                'stat' => true,
            ]
        ];
    }
}
