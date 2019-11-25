<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;

/**
 * 中文处理
 *
 * @author William Chan <root@williamchan.me>
 */
class Chinese
{
    /**
     * 繁体转简体
     * @param string $text
     * @param string $delim 连接符
     * @return string
     */
    public static function t2s($text, $delim = '')
    {
        static $table = null;
        if ($table === null) {
            $table = unserialize(file_get_contents(Yii::getAlias('@app/static/cert/t2s.dat')));
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < count($chars); $i++) {
            if (isset($table[$chars[$i]])) {
                $chars[$i] = $table[$chars[$i]];
            }
        }
        return implode($delim, $chars);
    }

    /**
     * 简体转繁体
     * @param string $text
     * @param string $delim 连接符
     * @return string
     */
    public static function s2t($text, $delim = '')
    {
        static $table = null;
        if ($table === null) {
            $table = unserialize(file_get_contents(Yii::getAlias('@app/static/cert/s2t.dat')));
        }
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i < count($chars); $i++) {
            if (isset($table[$chars[$i]])) {
                $chars[$i] = $table[$chars[$i]];
            }
        }
        return implode($delim, $chars);
    }

    /**
     * 将数字转为中文表述
     * @param int $num 数字
     * @param bool $unit 是否添加百千万等单位
     * @return string
     */
    public static function number($num, $unit = false)
    {
        static $chars = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        static $units = ['千' => 1000, '百' => 100, '十' => 10];
        if ($unit === false) {
            $str = '';
            for ($i = 0; $i < strlen($num); $i++) {
                $c = (int) substr($num, $i, 1);
                $str .= $chars[$c];
            }
            return $str;
        }
        $parts = [];
        if ($num >= 10000) {
            $parts[] = static::number(intval($num / 10000), $unit) . '万';
            $num %= 10000;
        }
        foreach ($units as $zh => $n) {
            if ($num >= $n) {
                $c = intval($num / $n);
                if ($n === 10 && count($parts) === 0 && $num < 20) {
                    $parts[] = $zh;
                } else {
                    $parts[] = $chars[$c] . $zh;
                }
                $num %= $n;
            } elseif (count($parts) > 0 && $parts[count($parts) - 1] !== $chars[0]) {
                $parts[] = $chars[0];
            }
        }
        if ($num > 0) {
            $parts[] = $chars[$num];
        } elseif (count($parts) > 0 && $parts[count($parts) - 1] === $chars[0]) {
            unset($parts[count($parts) - 1]);
        }
        return implode('', $parts);
    }

    /**
     * 转换为 utf-8
     * @param string $text
     * @param string $from 源字符集
     * @return string
     */
    public static function toUtf8($text, $from = 'gbk')
    {
        return mb_convert_encoding($text, 'utf-8', $from);
    }

    /**
     * 转换为 gbk
     * @param string $text
     * @param string $from 源字符集
     * @return string
     */
    public static function toGbk($text, $from = 'utf-8')
    {
        return mb_convert_encoding($text, 'gbk', $from);
    }
}
