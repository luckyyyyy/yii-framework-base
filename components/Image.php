<?php
/*
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use app\components\Chinese;
use Yii;
use yii\base\BaseObject;

/**
 * 图片生成器
 *
 * @property string $font
 * @property int $size
 * @property-write string $color
 * @property-write float $lineSpacing
 * @property-read string $bgFile
 *
 * @author William Chan <root@williamchan.me>
 */
class Image extends BaseObject
{
    /**
     * @var resource 图像句柄
     */
    public $im;
    private $_font;
    private $_size;
    private $_color;
    private $_lineSpacing;
    private $_vertical;
    private $_traditional;

    /**
     * 设置背景文件
     * @param string $file
     * @return static
     */
    public function setBgFile($file)
    {
        $this->im = $this->loadFile($file);
        $this->_color = $this->_lineSpacing = null;
        return $this;
    }

    /**
     * 设置字体文件
     * @param string $file 字体文件名或路径
     * @return static
     */
    public function setFont($file)
    {
        if (strpos($file, '/') === false) {
            $file = '@app/static/cert/' . $file;
        }
        $this->_font = Yii::getAlias($file);
        return $this;
    }

    /**
     * 获取字体文件
     * @return string
     */
    public function getFont()
    {
        if ($this->_font === null) {
            $this->_font = Yii::getAlias('@app/static/cert/simfang.ttf');
        }
        return $this->_font;
    }

    /**
     * 设置字体大小
     * @param int $size
     * @return static
     */
    public function setSize($size)
    {
        $this->_size = intval($size);
        return $this;
    }

    /**
     * 获取字体大小
     * @return string
     */
    public function getSize()
    {
        if ($this->_size === null) {
            $this->_size = 14;
        }
        return $this->_size;
    }

    /**
     * 设置颜色
     * @param string $hex
     * @return static
     */
    public function setColor($hex)
    {
        static $cache = [];
        if (isset($cache[$hex])) {
            $this->_color = $cache[$hex];
        } else {
            $rgb = [0, 0, 0];
            if (substr($hex, 0, 1) === '#') {
                $hex = substr($hex, 1);
            }
            if (strlen($hex) > 5) {
                for ($i = 0; $i < count($rgb); $i++) {
                    $rgb[$i] = hexdec(substr($hex, $i * 2, 2));
                }
            } elseif (strlen($hex) > 2) {
                for ($i = 0; $i < count($rgb); $i++) {
                    $rgb[$i] = hexdec(substr($hex, $i, 1)) * 16;
                }
            }
            $this->_color = $cache[$hex] = ImageColorAllocate($this->im, $rgb[0], $rgb[1], $rgb[2]);
        }
        return $this;
    }

    /**
     * 获取颜色
     * @param string $hex
     * @return int
     */
    public function getColor($hex = null)
    {
        if ($hex !== null) {
            $this->setColor($hex);
        }
        return $this->_color;
    }

    /**
     * 设置文字行间距
     * @param float $value
     * @return static
     */
    public function setLineSpacing($value)
    {
        $this->_lineSpacing = floatval($value);
        return $this;
    }

    /**
     * 批量设置文字参数
     * @param array $params
     * @return static
     */
    public function setTextParams($params)
    {
        isset($params['size']) && $this->setSize($params['size']);
        isset($params['color']) && $this->setColor($params['color']);
        isset($params['lincespacing']) && $this->setLineSpacing($params['lincespacing']);
        isset($params['font']) && $this->setFont($params['font']);
        if (isset($params['vertical'])) {
            $this->_vertical = $params['vertical'];
        }
        if (isset($params['traditional'])) {
            $this->_traditional = $params['traditional'];
        }
        return $this;
    }

    /**
     * 附加内置字串
     * @param string $text
     * @param int $x
     * @param int $y
     * @param int $font
     * @return static
     */
    public function addString($text, $x, $y, $font = 4)
    {
        ImageString($this->im, $font, $x, $y, $text, $this->_color);
        return $this;
    }

    /**
     * 附加文字
     * @param string $text 文字内容
     * @param int $x 首字左下角x
     * @param int $y 首字左下角y
     * @param int|null $w 位置总宽度（用于居中，负数右对齐）
     * @param string $charset 编码
     * @return static
     */
    public function addText($text, $x, $y, $w = null, $charset = 'utf8')
    {
        $extra = [];
        if ($this->_lineSpacing !== null) {
            $extra['linespacing'] = $this->_lineSpacing;
        }
        $info = ImageFTBBox($this->getSize(), 0, $this->getFont(), $text, $extra);
        $rw = $info[2] - $info[0];
        if ($w !== null) {
            if ($w >= 0) {
                $len = (strlen($text) + mb_strlen($text, $charset)) / 2;
                // 计算总占宽
                $dimensions = ImageFTBBox($this->getSize(), 0, $this->getFont(), $text);
                $textWidth = abs($dimensions[4] - $dimensions[0]);
                // 计算每个字符的长度
                $singleW = $textWidth / $len;
                // 计算每行最多容纳多少个字符
                $maxCount = floor($w / $singleW);

                $result = [];
                while ($len > $maxCount) {
                    // 成功取得一行
                    $result[] = mb_strimwidth($text, 0, $maxCount, '', $charset);
                    // 移除上一行的字符
                    $text = str_replace($result[count($result) - 1], '', $text);
                    // 重新计算长度
                    $len = (strlen($text) + mb_strlen($text, $charset)) / 2;
                }
                $result[] = $text;

                if (count($result) > 1) {
                    $result = array_slice($result, 0, 3);
                    $text = implode("\n", $result);
                    if (count($result) >= 3) {
                        $text = mb_substr($text, 0, -1) . "...";
                    }
                } else {
                    $text = $result[0];
                }

                // $text过长需要重新计算$rw
                $info = ImageFTBBox($this->getSize(), 0, $this->getFont(), $text, $extra);
                $rw = $info[2] - $info[0];
                $x += intval(($w - $rw) / 2);
            } else {
                $x -= $rw;
            }
        }
        ImageFTText($this->im, $this->getSize(), 0, $x, $y, $this->_color, $this->getFont(), $text, $extra);
        return $this;
    }

    /**
     * 附加竖向文字
     * @param string $text 文字内容
     * @param int $x 首字左下角x
     * @param int $y 首字左下角y
     * @param int|null $h 位置总高度（用于居中，负数下对齐）
     * @return static
     */
    public function addVerticalText($text, $x, $y, $h = null)
    {
        $extra = [];
        if ($this->_lineSpacing !== null) {
            $extra['linespacing'] = $this->_lineSpacing;
        }
        if (strpos($text, "\n") === false) {
            $text = implode("\n", preg_split('//u', $text));
        }
        if ($h !== null) {
            $info = ImageFTBBox($this->getSize(), 0, $this->getFont(), $text, $extra);
            $rh = $info[1] - $info[7];
            if ($y >= 0) {
                $y += intval(($h - $rh) / 2);
            } else {
                $y -= $rh;
            }
        }
        ImageFTText($this->im, $this->getSize(), 0, $x, $y, $this->_color, $this->getFont(), $text, $extra);
        return $this;
    }

    /**
     * 按参数添加文字
     * @param string $text
     * @param array $params
     * @return static
     */
    public function addTextWithParams($text, $params = [])
    {
        $this->setTextParams($params);
        if (isset($params['cnum'])) {
            $text = preg_replace_callback('/\d+/', function ($match) use ($params) {
                return Chinese::number((int) $match[0], $params['cnum']);
            }, $text);
        }
        if ($this->_traditional === true) {
            $text = Chinese::s2t($text);
        }
        if ($this->_vertical === true) {
            $this->addVerticalText($text, $params['x'], $params['y'], isset($params['h']) ? $params['h'] : null);
        } else {
            $this->addText($text, $params['x'], $params['y'], isset($params['w']) ? $params['w'] : null);
        }
        return $this;
    }

    /**
     * 附加图像
     * @param string $file
     * @param int $x 位置x
     * @param int $y 位置y
     * @param int $w 位置w
     * @param int $h 位置h
     * @param int|null $flip
     * @return static
     */
    public function addImage($file, $x, $y, $w, $h, $flip = null)
    {
        if (($im = $this->loadFile($file)) === false) {
            return $this;
        }
        $_w = ImageSX($im);
        $_h = ImageSY($im);
        $_r = min(1, $w / $_w, $h / $_h);

        $rw = intval($_r * $_w);
        $rh = intval($_r * $_h);
        $x += ($w - $rw) / 2;
        $y += ($w - $rh) / 2;

        if ($flip !== null) {
            ImageFlip($im, $flip);
        }
        ImageCopyResampled($this->im, $im, $x, $y, 0, 0, $rw, $rh, $_w, $_h);
        ImageDestroy($im);
        return $this;
    }

    /**
     * 输出 PNG 数据
     * @return string
     */
    public function toPng()
    {
        ob_start();
        ImagePng($this->im);
        ImageDestroy($this->im);
        $this->im = null;
        return ob_get_clean();
    }

    /**
     * 输出 JPG 数据
     * @return string
     */
    public function toJpg()
    {
        ob_start();
        ImageJpeg($this->im);
        ImageDestroy($this->im);
        $this->im = null;
        return ob_get_clean();
    }

    /**
     * @param string $file
     * @return resource
     * @see http://php.net/manual/en/ref.image.php
     * imagecreatefrombmp — Create a new image from file or URL
     * imagecreatefromgd2 — Create a new image from GD2 file or URL
     * imagecreatefromgd2part — Create a new image from a given part of GD2 file or URL
     * imagecreatefromgd — Create a new image from GD file or URL
     * imagecreatefromgif — Create a new image from file or URL
     * imagecreatefromjpeg — Create a new image from file or URL
     * imagecreatefrompng — Create a new image from file or URL
     * imagecreatefromstring — Create a new image from the image stream in the string
     * imagecreatefromwbmp — Create a new image from file or URL
     * imagecreatefromwebp — Create a new image from file or URL
     * imagecreatefromxbm — Create a new image from file or URL
     * imagecreatefromxpm — Create a new image from file or URL
     */
    private function loadFile($file)
    {
        if (substr($file, 0, 1) === '@') {
            $file = Yii::getAlias($file);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file);
            switch ($mimeType) {
                case 'image/jpg':
                    return @ImageCreateFromJpeg($file);
                case 'image/jpeg':
                    return @ImageCreateFromJpeg($file);
                case 'image/png':
                    return @ImageCreateFromPng($file);
                case 'image/gif':
                    return @ImageCreateFromGif($file);
                default:
                    return false;
            }
        } else {
            if (substr($file, 0, 4) === 'http') {
                $file = Muggle::guzzleHttpRequest('GET', $file, [], ['basic' => true]);
            }
            return @imagecreatefromstring($file);
        }
    }

    /**
     * 裁剪成圆形
     * @param string $url
     * @param int $dst_x 距离左侧距离
     * @param int $dst_y 距离上侧距离
     * @param int $w 宽
     * @param int $h 高
     * @return $this
     */
    public function addRoundImage($url, $dst_x, $dst_y, $w, $h)
    {
        $ims = $this->addAutoSizeImage($url, $w, $h);
        $_w = imagesx($ims); // 获取图片的宽
        $_h = imagesy($ims); // 获取图片的高
        $newPic = imagecreatetruecolor($_w, $_h);
        imagealphablending($newPic, false);
        $transparent = imagecolorallocatealpha($newPic, 0, 0, 0, 127);
        $r = $_h / 2;
        for ($x = 0; $x < $_w; $x++) {
            for ($y = 0; $y < $_h; $y++) {
                $c = imagecolorat($ims, $x, $y);
                $_x = $x - $_w / 2;
                $_y = $y - $_h / 2;
                if ((($_x * $_x) + ($_y * $_y)) < ($r * $r)) {
                    imagesetpixel($newPic, $x, $y, $c);
                } else {
                    imagesetpixel($newPic, $x, $y, $transparent);
                }
            }
        }

        ImageCopyResampled($this->im, $newPic, $dst_x, $dst_y, 0, 0, $w, $h, $_w, $_h);
        imagedestroy($newPic);
        return $this;
    }


    /**
     * 变更图片尺寸
     * @param string $url 图片url
     * @param int $w 宽
     * @param int $h 高
     * @return resource
     */
    public function addAutoSizeImage($url, $w, $h)
    {
        $im = $this->loadFile($url);
        $rx = imagesx($im); // 获取图片的宽
        $ry = imagesy($im); // 获取图片的高
        // 缩略后的大小
        $xx = $h;
        $yy = $w;
        if ($rx > $ry) {
            // 图片宽大于高
            $sx = abs(($ry-$rx) / 2);
            $sy = 0;
            $src_w = $ry;
            $src_h = $ry;
        } else {
            // 图片高大于等于宽
            $sy = abs(($rx-$ry) / 2.5);
            $sx = 0;
            $src_w = $rx;
            $src_h = $rx;
        }
        /* dst_image 目标图象连接资源。
        src_image源图象连接资源。
        dst_x目标 X 坐标点。
        dst_y目标 Y 坐标点。
        src_x源的 X 坐标点。
        src_y源的 Y 坐标点。
        dst_w目标宽度。
        dst_h目标高度。
        src_w源图象的宽度。
        src_h源图象的高度。*/
        $im2 = imagecreatetruecolor($w, $h);
        imagecopyresized($im2, $im, 0, 0, $sx, $sy, $yy, $xx, $src_w, $src_h);
        return $im2;
    }

    /**
     * 添加圆角图片
     * @param string $url 图片url
     * @param int $radius 圆角
     * @param int $dst_x 距离左侧距离
     * @param int $dst_y 距离上侧距离
     * @param int $w 宽
     * @param int $h 高
     * @return $this
     */
    public function addRadiusImage($url, $radius, $dst_x, $dst_y, $w, $h)
    {
        $resource = $this->addAutoSizeImage($url, $w, $h);
        $image_width = imagesx($resource); // 获取图片的宽
        $image_height = imagesy($resource); // 获取图片的高

        // lt(左上角)
        $lt_corner = imagecreatetruecolor($radius, $radius);    // 创建一个正方形的图像
        $bgColor = imagecolorallocate($lt_corner, 255, 255, 255);     // 图像的背景
        $fgColor = imagecolorallocate($lt_corner, 0, 0, 0);
        imagefill($lt_corner, 0, 0, $bgColor);
        // $radius,$radius：以图像的右下角开始画弧
        // $radius*2, $radius*2：以宽度、高度画弧
        // 180, 270：指定了角度的起始和结束点
        // fgColor：指定颜色
        imagefilledarc($lt_corner, $radius, $radius, $radius * 2, $radius * 2, 180, 270, $fgColor, IMG_ARC_PIE);
        // 将弧角图片的颜色设置为透明
        imagecolortransparent($lt_corner, $fgColor);

        imagecopymerge($resource, $lt_corner, 0, 0, 0, 0, $radius, $radius, 100);

        // rt(右下角)
        $rt_corner	= imagerotate($lt_corner, 270, 0);
        imagecopymerge($resource, $rt_corner, $image_width - $radius, 0, 0, 0, $radius, $radius, 100);

        ImageCopyResampled($this->im, $resource, $dst_x, $dst_y, 0, 0, $w, $h, $image_width, $image_height);
        imagedestroy($resource);
        return $this;
    }
}
