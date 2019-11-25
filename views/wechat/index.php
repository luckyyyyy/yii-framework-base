<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */

use yii\helpers\Url;

?>
<style>
 *{padding: 0;margin: 0;box-sizing:border-box}
body{padding: 10px;background: #0a1e3f;color: #eee;}
.step1{text-align:center;}
.step1 .title{margin-bottom: 10px;}
.step1 .qrcode{max-width: 80%;margin: 10px;}
.step1 a{color: #03a9f4;}
.scanned {
    display: none;
}
.step1.check-4 .scanned {
    display: block;
}
</style>

<?php if ($step === 1): ?>
<!-- STEP 1 -->
<?php $this->title = '需要授权'; ?>
<div id="main" class="step1">
    <h1 class="title">扫码授权</h1>
    <h2 class="scanned">已扫码，请在手机上完成操作。<h2>
    <img id="qrcode" class="qrcode" src="<?=Url::to('image/' . $scenario)?>">
    <p>登陆授权过期了</p>
    <p>长按屏幕不能扫了 想办法拿手机摄像头扫这个吧</p>
    <p><a href="javascript:window.location.reload()">手动刷新</a></p>
</div>
<script pos="ready">
    setTimeout(() => {
        window.location.reload();
    }, 15 * 1000);
    function check() {
        $.getJSON('<?= Url::to('check/' . $scenario) ?>?_' + new Date().getTime()).done(function(data) {
            if (data.result === true) {
                window.location.reload();
            } else if (data.result === 4) {
                $('#main')
                .removeClass('check-1')
                .removeClass('check-4')
                .removeClass('check-0')
                .addClass('check-' + data.result);
            } else if (data.result === -1) {
                window.location.reload();
            }
            setTimeout(check, 1000);
        });
    }
    check();
</script>

<?php elseif ($step === 2) : ?>
<!-- STEP 2 -->
<?php $this->title = '数据页面'; ?>
<style>

@font-face {font-family: "iconfont";
  src: url('//at.alicdn.com/t/font_725776_vtzarouvpdd.eot?t=1530200858524'); /* IE9*/
  src: url('//at.alicdn.com/t/font_725776_vtzarouvpdd.eot?t=1530200858524#iefix') format('embedded-opentype'), /* IE6-IE8 */
    url('data:application/x-font-woff;charset=utf-8;base64,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') format('woff'),
    url('//at.alicdn.com/t/font_725776_vtzarouvpdd.ttf?t=1530200858524') format('truetype'), /* chrome, firefox, opera, Safari, Android, iOS 4.2+*/
    url('//at.alicdn.com/t/font_725776_vtzarouvpdd.svg?t=1530200858524#iconfont') format('svg'); /* iOS 4.1- */
}

.iconfont {
    font-family:"iconfont" !important;
    font-size:16px;
    font-style:normal;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.icon-dianzan:before { content: "\e600"; }
.icon-yuedu:before { content: "\e639"; }
.icon-pinglun:before { content: "\e664"; }

body, html {
    height: 100%;
    max-height: 100%;
    padding: 0;
    margin: 0;
    min-width: 1680px;
}
@font-face {
    font-family: 'DINCond-Bold';
    src: url('/font/DINCond-Bold.otf');
}
@font-face {
    font-family: 'SourceHanSansSC-Light';
    src: url('/font/SourceHanSansSC-Light.otf');
}

.main {
    flex-wrap: wrap;
    display: flex;
}
.main .ceil {
    flex: 0 0 50%;
    padding: 5px;
    position: relative;
}
.item {
    display: flex;
    /* background: linear-gradient(to bottom,  #34445d 0%, #34445d 100%); */
    background: #34445d;
    color: #f2f2f2;
    font-family: 'DINCond-Bold';
    font-weight: 100;
    /* font-weight:bold; */
}
.item a {
    color: white;
    text-decoration: none;
}
.item .right {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    margin: 20px 0;
    flex: 1;
    /* margin-right: 20px; */
}
.item .title {
    font-family: 'SourceHanSansSC-Light';
    font-weight: bold;
    font-size: 40px;
    margin-right: 20px;
    overflow : hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
}
.item .left {
    padding: 20px;
}
.item img {
    height: 180px;
}
.item .bottom {
    font-size: 54px;
    display: flex;
}
.item .bottom .iconfont {
    font-size: 52px;
    padding-right: 10px;
    opacity: .7;
    font-weight: bold;
}
.item .bottom span {
    flex: 1;
}
.main .ceil {
    text-shadow: 1px 1px 1px #333;
}
.main .ceil:first-child {
    width: 100%;
    flex: 0 0 100%;
}

/* .main .ceil:first-child .item {
    background: linear-gradient(to bottom,  #1c7bd0 0%,#1e3dcd 100%);
}
.main .ceil:nth-child(2) .item {
    background: linear-gradient(to bottom,  #fc8a23 0%,#fc6f27 100%);
}
.main .ceil:nth-child(3) .item {
    background: linear-gradient(to bottom,  #25c7ab 0%,#1c98a1 100%);
}
.main .ceil:nth-child(4) .item {
    background: linear-gradient(to bottom,  #cf00df 0%,#D332D0 100%);
}
.main .ceil:nth-child(5) .item {
    background: linear-gradient(to bottom,  #70D5F4 0%,#00B9F2 100%);
}
.main .ceil:nth-child(6) .item {
    background: linear-gradient(to bottom,  #fc713a 0%,#fa0c50 100%);
}
.main .ceil:nth-child(7) .item {
    background: linear-gradient(to bottom,  #fc713a 0%,#fa0c50 100%);
}
.main .ceil:nth-child(8) .item {
    background: linear-gradient(to bottom,  #fc713a 0%,#fa0c50 100%);
} */
.main .ceil:first-child .item .title {
    font-size: 56px;
}
.main .ceil:first-child .item .bottom span {
    font-size: 80px;
}
.main .ceil:first-child .item .bottom .iconfont {
    font-size: 80px;
}
.main .ceil:first-child .item img {
    height: 250px;
}
</style>
<div class="main" id="main">Loading...</div>
<script pos="ready">
    setTimeout(() => {
        window.location.reload();
    }, 60 * 60 * 1000);
    function getData() {
        $.getJSON('<?=Url::to('appmsg/' . $scenario)?>').done(function(data) {
            if (data.sent_list && data.sent_list[0]) {
                var html = '';
                data.sent_list[0].appmsg_info.forEach(function(item, index) {
                    html += '<div class="ceil">\
                        <div class="item">\
                            <div class="left">\
                                <img src="' + item.cover + '">\
                            </div>\
                            <div class="right">\
                                <div class="title"><a target="_blank" href="' + item.content_url + '">' + item.title + '</a></div>\
                                <div class="bottom">\
                                    <span><i class="iconfont icon-yuedu"></i>' + item.read_num + '</span>\
                                    <span><i class="iconfont icon-dianzan"></i>' + item.like_num + '</span>\
                                    <span><i class="iconfont icon-pinglun"></i>' + item.comment_num + '</span>\
                                </div>\
                            </div>\
                        </div>\
                    </div>';
                });
                $('#main').html(html);
                setTimeout(getData, 5000);
            } else {
                setTimeout(function() {
                    window.location.reload();
                }, 5000);
            }
        }).fail((function() {
            window.location.reload();
        }));
    }
    getData();
</script>
<?php endif; ?>
