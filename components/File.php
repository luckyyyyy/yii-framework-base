<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use app\models\Attachment;
use Yii;
use yii\base\BaseObject;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use yii\helpers\ArrayHelper;
use yii\db\Expression;
use yii\base\Exception;

/**
 * 文件模块 用于处理上传文件
 *
 * @author William Chan <root@williamchan.me>
 *
 */
class File extends BaseObject
{
    const TYPE_AVATAR = 'avatar';
    const TYPE_TEMP = 'temp';

    /**
     * @var string 最终通过效验的文件名 也可以指定
     */
    public $name;

    /**
     * @var string 最终上传的完整路径 也可以强行指定
     */
    public $fullPath;

    /**
     * @var string 类型 决定文件上传路径
     */
    public $type = self::TYPE_TEMP;

    /**
     * @var UploadedFile
     */
    public $upload;

    /**
     * @var int 文件最大尺寸
     */
    public $maxSize = 10 * 1048576;

    /**
     * @var array 后缀限制
     */
    public $extensions = ['jpg', 'png', 'gif', 'jpeg'];

    /**
     * @var string 出错说明
     */
    public $error;

    /**
     * 构造函数
     * @param string $name
     * @param array $config
     */
    public function __construct($name = null, $config = [])
    {
        if (is_array($name)) {
            $config = $name;
        } else {
            $config['name'] = $name;
        }
        parent::__construct($config);
    }

    /**
     * 读取文件 注意 这里极度消耗内存 慎用
     * @return string 文件路径
     * @return string
     */
    public function loadFile()
    {
        if ($this->upload) {
            ini_set('memory_limit', '512M');
            set_time_limit(0);
            return @file_get_contents($this->upload->tempName);
        }
    }

    /**
     * 把文件上传到😓存储
     * @param string $name
     * @return bool 上传成功或上传失败
     */
    public function uploadCloud($name)
    {
        if ($this->validate($name)) {
            $storage = Yii::$app->storage;
            if (!$storage->putObject($this->fullPath, $this->loadFile())) {
                $this->error = $storage->getLastError();
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * 文件保存到本地
     * @return string 文件路径
     * @return bool 上传成功或上传失败
     */
    public function uploadLocal($name)
    {
        throw new \yii\base\NotSupportedException();
    }

    /**
     * 获取的错误信息
     * @return string
     */
    public function getLastError()
    {
        return $this->error;
    }

    /**
     * 获取当前文件的 mimetype
     * yii has a helper, but not use mime_content_type
     * @return string|null
     */
    public function getMimeType()
    {
        if ($this->upload) {
            return FileHelper::getMimeType($this->upload->tempName);
        }
    }
    /**
     * 从表单中获取文件并效验
     * @param string $name
     * @return bool 成功返回 true 失败返回 false
     */
    public function validate($name)
    {
        if ($this->fetchUploadedFile($name)) {
            if ($this->upload->size > $this->maxSize) {
                $this->error = sprintf('文件不能超过：%gMB', $this->maxSize / 1048576);
            } else {
                $extension = $this->upload->extension;
                if (empty($extension)) {
                    $mime = $this->getMimeType();
                    if (!strncmp($mime, 'image/', 6) || !strncmp($mime, 'audio/', 6)) {
                        $extension = substr($mime, 6);
                    }
                } elseif ($extension === 'jpeg') {
                    $extension = 'jpg';
                }
                $extension = strtolower($extension);
                if (count($this->extensions) > 0 && !in_array($extension, $this->extensions)) {
                    $this->error = '文件格式必须是：' . implode('|', $this->extensions);
                } else {
                    if ($this->name === null) {
                        $this->name = static::createFileName($extension);
                    }
                    if ($this->fullPath === null) {
                        $this->fullPath = static::createFileSavePath($this->type) . '/' . $this->name;
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 从UploadedFile中获取文件
     * @param string $name
     * @return bool 成功返回 true 失败返回 false
     */
    private function fetchUploadedFile($name)
    {
        $upload = UploadedFile::getInstanceByName($name);
        if (!$upload || $upload->hasError) {
            /* @see http://www.php.net/manual/en/features.file-upload.errors.php */
            $this->error = $upload ? '上传失败：' . $upload->error . ' !== UPLOAD_ERR_OK' : '文件未上传';
            return false;
        } else {
            $this->upload = $upload;
            return true;
        }
    }

    /* --- implements Interface --- */

    /**
     * 生成一个随机文件名
     * @param string $ext
     * @return string
     */
    public static function createFileName($ext = null)
    {
        return uniqid('up_', true) . ($ext ? '.' . $ext : '');
    }

    /**
     * 根据分类获取文件的保存路径
     * @param string $type 类型
     * @param int $id 扩展参数ID 部分资源可以按位处理分区
     * @return string
     */
    public static function createFileSavePath($type = self::TYPE_TEMP, $id = 0)
    {
        $time = date('Ym');
        if ($id === 0 && Yii::$app->user->id) {
            $id = Yii::$app->user->id;
        }
        switch ($type) {
            case self::TYPE_AVATAR:
                return sprintf('/avatar/%02x', $id & 0x1f);
            case self::TYPE_TEMP:
                return sprintf('/uploads/attachment/%d', $time);
            default:
                return sprintf('/uploads/static/%s/%d', $type, $time);
        }
    }

    /**
     * 查询一个文件的路径支持从微信和数据库查询
     * @param mixed $condition
     * @param string $type 文件的类型
     * @param string $queue 是否使用队列
     * @return string|null
     * @throws Exception
     */
    public static function findOne($condition, $type = self::TYPE_TEMP, $queue = 'queue2')
    {
        if (static::checkCondition($condition)) {
            if (is_numeric($condition)) {
                $condition = ['id' => $condition];
                if (!Yii::$app->user->isAdmin('%')) {
                    $condition['identity_id'] = Yii::$app->user->id ?? 0;
                }
                $model = Attachment::find()->where($condition)->one();
                if ($model) {
                    return $model->path;
                }
            } elseif (is_string($condition)) {
                $parse = parse_url($condition);
                $path = static::createFileSavePath($type) . '/' . File::createFileName();
                $media_id = ltrim($parse['path'], '/');
                if ($queue) {
                    Yii::$app->get($queue)->pushDownloadMedia($parse['host'], $media_id, $path);
                } else {
                    Yii::$app->get('wechatApp')->setScenario($parse['host'])->fetchMediaFile($media_id, $path);
                }
                return $path;
            }
        }
        throw new Exception('no resources');
    }

    /**
     * 查询多个文件的路径（支持从微信和数据库查询，微信暂未实现）
     * @param mixed $condition
     * @return array
     */
    public static function findAll($condition)
    {
        $models = null;
        $result = [];
        $condition = $sort = ArrayHelper::getColumn($condition, 'id');
        if (count(array_filter($condition)) > 0) {
            $query = Attachment::find()->where(['in', 'id', $condition]);
            if (!Yii::$app->user->isAdmin('%')) {
                $query->andWhere(['identity_id' => Yii::$app->user->id ?? 0]);
            }
            $models = $query->orderBy(new Expression('FIELD(id, ' . implode(',', $sort) .')'))->all();
        }
        if ($models) {
            foreach ($models as $model) {
                $result[] = [
                    'id' => $model->id,
                    'url' => $model->path,
                ];
            }
        }
        return $result;
    }

    /**
     * 检查条件是否可被解析为文件 支持数字和 wechat://zhongce/{media_id}
     * @param mixed $condition
     * @return bool
     */
    public static function checkCondition($condition)
    {
        if (is_numeric($condition)) {
            return true;
        } elseif (is_string($condition)) {
            $parse = parse_url($condition);
            if (isset($parse['scheme']) && isset($parse['host']) && isset($parse['path']) && $parse['scheme'] === 'wechat') {
                return true;
            }
        }
    }
}
