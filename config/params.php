<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

$params = [
    // 'cmd.ffmpeg' => '/home/soft/ffmpeg/bin/ffmpeg', // ffmpeg 命令行
    'mob.url.prefix' => '', // 移动端 URL 前缀
    'host.api' => 'http://localhost/',// 文章阅读量列表地址
    'host.testing' => 'http://localhost/',// 众测地址
    'host.shortUrl' => 'http://t.cn/', // 短网址
    'xs.server.index' => '127.0.0.1:8383', // 迅搜服务器 索引服务
    'xs.server.search' => '127.0.0.1:8384', // 迅搜服务器 搜索服务
];
return $params;
