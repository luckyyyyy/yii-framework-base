<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

namespace app\components;

use Yii;
use yii\helpers\BaseHtml;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\base\Exception;

/**
 * Html 扩展
 * 一些垃圾方法全部丢在这里
 *
 * @author William Chan <root@williamchan.me>
 */
class Html extends BaseHtml
{

    /**
     * 人类友好时间表示
     * @param int $time
     * @param string $format
     * @return string
     */
    public static function humanTime($time, $format = 'Y/m/d')
    {
        $days = intval((time() - strtotime(date('Y-m-d', $time))) / 86400);
        $diff = date_diff(date_create(), date_create(date('Y-m-d H:i:s', $time)));
        if ($diff->y > 0) {
            return date($format, $time);
        } elseif ($diff->m > 0) {
            return date($format, $time);
            //return $diff->m . '个月前';
        } elseif ($days > 2) {
            return $days . '天前';
        } elseif ($days === 2) {
            return '前天 ' . date('H:i', $time);
        } elseif (($days === 1 && $diff->h > 4) || $diff->d > 0) {
            return '昨天 ' . date('H:i', $time);
        } elseif ($diff->h > 0) {
            return $diff->h . '小时前';
        } elseif ($diff->i > 0) {
            return $diff->i . '分钟前';
        } else {
            return '刚刚';
        }
    }

    /**
     * 人类友好时间（去除 时 分)
     * @param int $time
     * @param string $format
     * @return string
     */
    public static function humanShortTime($time, $format = 'n月j日')
    {
        $days = intval((time() - strtotime(date('Y-m-d', $time))) / 86400);
        if ($days === 0) {
            return '今天';
        } else {
            return date($format, $time);
        }
    }

    /**
     * 将秒数转换为友好的时长
     * @param int $second 总秒数
     * @param int $level 层级
     * @return string
     */
    public static function humanDuration($second, $level = 2)
    {
        $parts = [];
        if ($second > 86400) {
            $parts[] = sprintf('%d天', $second / 86400);
            $second %= 86400;
        }
        if ($second > 3600) {
            $parts[] = sprintf('%d小时', $second / 3600);
            $second %= 3600;
        }
        if ($second > 60) {
            $parts[] = sprintf('%d分钟', $second / 60);
            $second %= 60;
        }
        if ($second > 0) {
            $parts[] = sprintf('%d秒', $second);
        }
        return implode('', array_slice($parts, 0, $level));
    }

    /**
     * @param string $str
     * @return bool 是否为垃圾信息
     */
    public static function isSpam($str)
    {
        /*if ((strpos($str, '群') !== false || stripos($str, 'q') !== false) && preg_match('/\d[\d\W]{4,}\d/u', $str)) {
            return true;
        } elseif ((strpos($str, '约') !== false || strpos($str, '撩') !== false)
            && preg_match('/(?:湿|操|开车)/u', $str)
        ) {
            return true;
        }*/
        $others = array_merge(array_keys(static::filterWords()), [
            '习近平', '李克强', '胡锦涛', '温家宝', '江泽民', '蛤蟆', '暴力膜',
            '母狗', '韩正', '孙春兰', '胡春华', '刘鹤', '魏凤和', '王勇',
            '王毅', '肖捷', '赵克志', '19大', '十九大', '共产党', '王岐山',
        ]);
        foreach ($others as $other) {
            if (strpos($str, $other) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 替换非常规 utf-8 字符
     * @param string $str
     * @return string
     */
    public static function normalUtf8($str)
    {
        preg_match_all('/(?:[\x00-\x7f]|[\xe0-\xef][\x80-\xbf][\x80-\xbf])/', $str, $matches);
        return implode('', $matches[0]);
    }

    /**
     * 是否为常规 utf-8
     * @param string $str
     * @return bool
     */
    public static function isNormalUtf8($str)
    {
        $res = true;
        $len = strlen($str);
        for ($i = 0; $res && $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c >= 224 && $c < 240) {
                if (($len - $i) < 3) {
                    $res = false;
                } else {
                    $c1 = ord($str[++$i]);
                    $c2 = ord($str[++$i]);
                    if ($c1 <= 128 || $c1 >= 192 || $c2 <= 128 || $c2 >= 192) {
                        $res = false;
                    }
                }
            } elseif ($c > 128) {
                $res = false;
            }
        }
        return $res;
    }

    /**
     * 关键词表替换表
     * @return array
     */
    public static function filterWords()
    {
        return [
            '做爱' => '*爱',
            '精液' => '**',
            '骚逼' => '**',
            '文爱' => '*爱',
            '语爱' => '*爱',
            '鸡巴' => '杰*',
            '淫乱' => '*乱',
            '操我' => '*我',
            '援交' => '援*',
            '约炮' => '约*',
            '卖淫' => '**',
            '口交' => '**',
            '阴茎' => '**',
            '阴道' => '**',
            '阴蒂' => '**',
            '乳房' => '**',
            '乳头' => '**',
            '龟头' => '*头',
            '被操' => '被*',
            '撸管' => '*管',
            '强奸' => '强*',
            '木耳' => '*耳',
            '人肉' => '*肉',
            'ppp' => 'p*p',
            '小怪兽' => '小怪*',
            '啪啪啪' => '啪*啪',
            '巨根' => '巨*',
            '屌' => '*',
            '屄' => '*',
            '聊骚' => '聊*',
            '鸡鸡' => '**',
            'ke炮' => 'k??',
            '嗑炮' => 'k??',
        ];
    }

    /**
     * 污词替换
     * @param string $str
     * @return string
     */
    public static function filter($str)
    {
        return strtr($str, static::filterWords());
    }

    /**
     * 添加CDN必要前缀
     * @param array|string $url
     * @param bool $force
     * @return string
     */
    public static function extUrl($url, $force = false)
    {
        if (is_string($url)) {
            $parse_url = parse_url($url);
            if ($force || !isset($parse_url['host'])) {
                $url = '';
                if (isset($parse_url['path']) && !empty($parse_url['path'])) {
                    $url = Yii::$app->storage->host;
                    if (substr($parse_url['path'], 0, 1) !== '/') {
                        $url .= '/';
                    }
                    $url .= $parse_url['path'];
                    if (isset($parse_url['query'])) {
                        $url .= '?' . $parse_url['query'];
                    }
                }
            }
            $url = rawurldecode(urlencode($url));
            return $url;
        } elseif (is_array($url)) {
            foreach ($url as &$value) {
                if (is_array($value) && isset($value['url'])) {
                    $value['url'] = rawurldecode(urlencode(static::extUrl($value['url'], $force)));
                } else {
                    $value = rawurldecode(urlencode(static::extUrl($value, $force)));
                }
            }
            return $url;
        }
    }

    /**
     * 获取图片info信息（对于同地址有缓存）
     * 通过阿里云OSS获取，有网络请求，前端也可以直接在CDN上加 ?x-oss-process=image/info
     * 后缀获取需要走网络请求，所以非必要情况下请勿使用
     * @only 阿里云OSS上的图片
     * @param string $url
     * @return array
     */
    public static function getImageInfo($url)
    {
        if (is_string($url)) {
            $cache = Yii::$app->cache;
            $key = 'IMAGE_INFO_' . $url;
            $info = $cache->get($key);
            if (!$info) {
                $info = Yii::$app->storage->getImageInfo($url);
                if (!empty($info)) {
                    $hue = Yii::$app->storage->getImageHue($url);
                    $info = array_merge(Json::decode($info), Json::decode($hue));
                    $cache->set($key, $info, 86400);
                }
            }
            return static::formatImageInfo($info, $url);
        } elseif (is_array($url)) {
            $result = [];
            foreach ($url as $value) {
                if (is_array($value) && isset($value['url'])) {
                    $result[] = static::getImageInfo($value['url']);
                } else {
                    $result[] = static::getImageInfo($value);
                }
            }
            return $result;
        }
    }

    /**
     * 数组转 XML
     * @param array $a
     * @param string $tag
     * @return string
     */
    public static function arrayToXml($a, $tag = 'xml')
    {
        $x = '<' . $tag . '>';
        foreach ($a as $key => $value) {
            if (is_array($value)) {
                $x .= static::arrayToXml($value, $key);
            } else {
                $x .= '<' . $key . '>' . (is_numeric($value) ? $value : '<![CDATA[' . $value . ']]>') . '</' . $key . '>';
            }
        }
        $x .= '</' . $tag . '>';
        return $x;
    }

    /**
     * XML 转数组
     * @param string $x
     * @return bool|array
     */
    public static function xmlToArray($x)
    {
        $x = @simplexml_load_string($x, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($x === false) {
            return false;
        }
        return json_decode(json_encode($x), true);
    }


    /**
     * 修正批量插入时的二维数组
     * @param array $array
     * @return array
     */
    public static function fixBatchInsert(array $array)
    {
        foreach ($array as $k => &$v) {
            foreach ($v as $key => $value) {
                if (is_array($value)) {
                    $v[$key] = Json::encode($value);
                }
            }
        }
        return $array;
    }

    /**
     * 格式化图片info信息
     * @see https://help.aliyun.com/document_detail/44975.html?spm=5176.doc44688.6.942.H7SnDi#h2-url-1
     * @param array|null $info
     * @param string $url
     * @return array
     *
     */
    private static function formatImageInfo($info, $url)
    {
        // exif
        $result = [
            'url' => static::extUrl($url),
        ];
        if (!empty($info)) {
            foreach ($info as $key => $value) {
                if ($key === 'RGB') {
                    $key = strtolower($key);
                    $value = substr($value, 2);
                }
                $result[lcfirst($key)] = $value['value'] ?? $value;
            }
            $result['isGif'] = $result['format'] === 'gif';
        }

        return $result;
    }

}
