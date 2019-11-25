<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 */
namespace app\components;

use Yii;

/**
 * Wechat message auto-reply
 *
 * ```php
 * $wx = new WechatReplier($config = []);
 * $wx->addHandler(bool function (WechatReplier $wx) {
 * });
 * $wx->on('click.banned', function(WechatReplier $wx) {
 *    // image, text, subscribe|hello|event.subscribe,
 * });
 * $wx->run();
 * ```
 *
 * @author William Chan <root@williamchan.me>
 * @see http://mp.weixin.qq.com/wiki/14/89b871b5466b19b3efa4ada8e577d45e.html
 */
class WechatReplier
{
    use LogTrait;

    /**
     * @var string 接口令牌
     */
    public $token;
    /**
     * @var string 公众平台应用ID
     */
    public $appId;
    /**
     * @var string 公众平台应用密钥
     */
    public $appSecret;
    /**
     * @var string 消息加解密密钥
     */
    public $encodingAesKey;
    /**
     * @var bool 是否开启调试日志
     */
    public $debug = false;
    /**
     * @var string 日志文件路径，设为 /dev/null 表示禁用
     */
    public $logFile = '/dev/null';
    /**
     * @var SimpleXMLElement 收到的消息体（XML结构）
     */
    public $in;

    /**
     * @var WechatCrypt
     */
    private $_crypt;
    private $_handlers;
    private $_autoRun;
    private $_msgType;
    private $_text;
    private $_mediaId;
    private $_news;
    private $_errorHandler;
    private $_exceptionHandler;

    /**
     * 构造函数
     * @param array 配置数组
     */
    public function __construct($config = [])
    {
        foreach ($config as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
        $this->_handlers = [];
        $this->_autoRun = true;
        $this->_news = [];
        // error & exception handler
        $this->_errorHandler = set_error_handler([$this, 'errorHandler']);
        $this->_exceptionHandler = set_exception_handler([$this, 'exceptionHandler']);
    }

    /**
     * 析构函数，必要时自动运行处理器
     */
    public function __destruct()
    {
        if ($this->_autoRun) {
            $this->run();
        }
    }

    /**
     * @param string $type 事件类型
     * @return bool 是否为事件消息或指定类型的事件消息
     */
    public function isEvent($type = null)
    {
        $in = $this->in;
        if ($in->MsgType == 'event') {
            return $type === null || (isset($in->Event) && $in->Event == $type);
        }
        return false;
    }

    /**
     * @return bool 是否为打招呼消息
     */
    public function isHello()
    {
        return $this->isEvent('subscribe') && !$this->getScanScene();
    }

    /**
     * @return bool 进入会话
     */
    public function isEnterSession()
    {
        return $this->isEvent('user_enter_tempsession');
    }

    /**
     * @return bool 是否为取关
     */
    public function isCancel()
    {
        return $this->isEvent('unsubscribe');
    }

    /**
     * @param string $key 点击值
     * @return bool 是否为相应的菜单点击事件消息
     */
    public function isClick($key = null)
    {
        if ($this->isEvent('CLICK')) {
            return $key === null || $key === $this->getEventKey();
        }
        return false;
    }

    /**
     * @param string $type 媒体类型
     * @return bool 是否为多媒体消息或指定的多媒体类型
     */
    public function isMedia($type = null)
    {
        $in = $this->in;
        if (!isset($in->MediaId)) {
            return false;
        } elseif ($type !== null) {
            return $in->MsgType == $type;
        } else {
            return true;
        }
    }

    /**
     * @return string|bool 扫码场景，若非扫码返回 false
     */
    public function getScanScene()
    {
        if ($this->isEvent('SCAN')) {
            return $this->getEventKey();
        } elseif ($this->isEvent('subscribe')) {
            $key = $this->getEventKey();
            if ($key !== false && !strncmp($key, 'qrscene_', 8)) {
                return substr($key, 8);
            }
        }
        return false;
    }

    /**
     * @return string|bool 事件类型，若非事件消息则返回 false
     */
    public function getEventType()
    {
        $in = $this->in;
        if ($in->MsgType == 'event') {
            return strval($in->Event);
        }
        return false;
    }

    /**
     * @return string|bool 事件值，若不包含 EventKey 则返回 false
     */
    public function getEventKey()
    {
        $in = $this->in;
        if (isset($in->EventKey)) {
            return strval($in->EventKey);
        }
        return false;
    }

    /**
     * @return string|bool 点击事件值，若非点击事件则返回 false
     */
    public function getClickKey()
    {
        return $this->isClick() ? $this->getEventKey() : false;
    }

    /**
     * @return string|bool 获取文本消息（含语音识别），若非文本则返回 false
     */
    public function getText()
    {
        $in = $this->in;
        if ($in->MsgType == 'text') {
            return trim($in->Content);
        } elseif ($in->MsgType == 'voice' && isset($in->Recognition)) {
            return trim($in->Recognition);
        }
        return false;
    }

    /**
     * @return string 返回的消息类型
     */
    public function getMsgType()
    {
        return $this->_msgType;
    }

    /**
     * 回复文本消息
     * @param string $text 文本内容
     * @return static
     */
    public function asText($text)
    {
        $this->_msgType = 'text';
        $this->_text = strval($text);
        return $this;
    }

    /**
     * 追加文本消息
     * @param string $text 追增的文本内容
     * @return static
     */
    public function addText($text)
    {
        if ($this->_msgType === null) {
            $this->_msgType = 'text';
        }
        $this->_text .= $text;
        return $this;
    }

    /**
     * 转发至多客服
     * @return static
     */
    public function asCustomer()
    {
        $this->_msgType = 'transfer_customer_service';
        return $this;
    }

    /**
     * 回复多媒体信息使用媒体ID
     * @param string $mediaId
     * @param string $msgType
     * @return static
     */
    public function asMedia($mediaId, $msgType = 'image')
    {
        $this->_mediaId = $mediaId;
        $this->_msgType = $msgType;
        return $this;
    }

    /**
     * 回复图片消息
     * @param string $file 图片文件路径或名称
     * @param string $content 图像内容
     * @return static
     */
    public function asImage($file, $content = null)
    {
        $this->_mediaId = $this->uploadMedia('image', $file, $content);
        if ($this->_mediaId !== null) {
            $this->_msgType = 'image';
        }
        return $this;
    }

    /**
     * 回复语音消息
     * @param string $file 语音文件或路径
     * @param string $content 语音内容
     * @return static
     */
    public function asVoice($file, $content = null)
    {
        $this->_mediaId = $this->uploadMedia('voice', $file, $content);
        if ($this->_mediaId !== null) {
            $this->_msgType = 'voice';
        }
        return $this;
    }

    /**
     * 回复音乐消息
     * @return static
     * @todo
     */
    public function asMusic()
    {
        return $this;
    }

    /**
     * 回复视频消息
     * @return static
     * @todo
     */
    public function asVideo()
    {
        return $this;
    }

    /**
     * 回复小程序（被动回复好像不支持，但是我以前好像看到过文档支持的）
     * @return static
     * @todo
     */
    public function asApp()
    {
        return $this;
    }

    /**
     * 回复空消息
     * @return static
     */
    public function asEmpty()
    {
        $this->_msgType = null;
        return $this;
    }

    /**
     * 添加一条图文消息
     * @param array|string $title 消息各字段组成的数组或者标题
     * @param string $url 点击后的网址
     * @param string $picurl 缩略图片网址
     * @param string $description 描述字
     * @param bool $prepend
     * @return static
     */
    public function addNews($title, $url = '', $picurl = '', $description = '', $prepend = false)
    {
        $item = is_array($title) ? array_merge([
            'title' => 'Untitled',
            'url' => '',
            'picurl' => '',
            'description' => '',
        ], $title) : [
            'title' => $title,
            'url' => $url,
            'picurl' => $picurl,
            'description' => $description,
        ];
        $this->_msgType = 'news';
        if ($prepend === true) {
            array_unshift($this->_news, $item);
        } else {
            $this->_news[] = $item;
        }
        return $this;
    }

    /**
     * 添加回复处理器
     * @param callable $handler 原型为 bool function(WechatReplier $wx) {}
     * @param string $key
     * @return static
     */
    public function addHandler(callable $handler, $key = null)
    {
        if ($key === null) {
            $this->_handlers[] = $handler;
        } else {
            $this->_handlers[$key] = $handler;
        }
        return $this;
    }

    /**
     * 添加回复处理器
     * @param string $type 消息类型，仅在消息类型匹配时才会调用
     * @param callable $handler 原型为 bool function(WechatReplier $wx) {}
     * @return static
     */
    public function on($type, callable $handler)
    {
        $this->_handlers[] = [$type, $handler];
        return $this;
    }

    /**
     * 运行所有处理器并输出 XML 消息体
     */
    public function run()
    {
        // prior checks
        $this->_autoRun = false;
        if (!$this->checkSignature()) {
            $this->error('Invalid signature');
        } elseif (isset($_GET['echostr'])) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $_GET['echostr'];
            exit(0);
        }
        // parse incoming msg
        $this->parseIncoming();
        // traverse handlers
        foreach ($this->_handlers as $handler) {
            if ($this->execHandler($handler) === true) {
                break;
            }
        }
        // outgoing
        if ($this->_msgType !== null) {
            $out = "<xml>\r\n<ToUserName><![CDATA[{$this->in->FromUserName}]]></ToUserName>\r\n"
                . "<FromUserName><![CDATA[{$this->in->ToUserName}]]></FromUserName>\r\n"
                . "<CreateTime>" . time() . "</CreateTime>\r\n"
                . "<MsgType><![CDATA[{$this->_msgType}]]></MsgType>\r\n"
                . $this->formatContent() . "</xml>\r\n";
            if ($this->_crypt !== null) {
                $out = $this->_crypt->encrypt($out);
            }
        } else {
            $out = 'success';
        }
        $this->debugLog('send reply: ' . $out);
        header('Content-Type: text/xml; charset=utf-8');
        echo $out;
        exit(0);
    }

    /**
     * 出错日志处理
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    public function errorHandler($errno, $errstr, $errfile = '', $errline = 0)
    {
        $this->log('[PHP Error #' . $errno . '] ' . $errstr . ' in `' . $errfile . '\' on line ' . $errline);
        if ($this->_errorHandler !== null) {
            call_user_func_array($this->_errorHandler, func_get_args());
        }
    }

    /**
     * 异常日志处理
     * @param \Exception $ex
     */
    public function exceptionHandler($ex)
    {
        $this->log('[PHP ' . get_class($ex) . '] ' . $ex->getMessage() . ' in `' . $ex->getFile() . '\' on line ' . $ex->getLine());
        if ($this->_exceptionHandler !== null) {
            call_user_func($this->_exceptionHandler, $ex);
        }
    }

    /**
     * @param callable|array $handler
     * @return bool
     */
    protected function execHandler($handler)
    {
        if (is_callable($handler)) {
            return call_user_func($handler, $this);
        } elseif (isset($handler[0]) && is_string($handler[0])) {
            $in = $this->in;
            $type = $handler[0];
            $isMatch = $type == $in->MsgType;
            if (!$isMatch && $in->MsgType == 'event') {
                if (($pos = strpos($type, '.')) !== false) {
                    $key = substr($type, $pos + 1);
                    $type = substr($type, 0, $pos);
                } elseif ($type == $in->Event) {
                    $key = null;
                }
                $isMatch = !strcasecmp($type, $in->Event) && isset($key) && ($key === null || $key == $in->EventKey);
            }
            if ($isMatch) {
                return call_user_func($handler[1], $this);
            }
        }
        return false;
    }

    /**
     * 上传媒体文件 文件名有唯一缓存
     * @param string $type 文件类型，支持：image, voice, video, thumb
     * @param string $file 文件路径或名称
     * @param string $content 文件内容
     * @return string|null 媒体文件 ID，若失败则返回 null
     */
    protected function uploadMedia($type, $file, $content = null)
    {
        return \Yii::$app->get('wechatApp')->uploadMedia($type, $file, $content);
    }

    protected function debugLog($msg)
    {
        if ($this->debug === true) {
            $this->log('[DEBUG] ' . $msg);
        }
    }

    protected function formatContent()
    {
        switch ($this->_msgType) {
            case 'text':
                return '<Content><![CDATA[' . $this->_text . ']]></Content>';
            case 'news':
                $items = '';
                $count = 0;
                foreach ($this->_news as $item) {
                    $items .= $this->formatNewsItem($item);
                    $count++;
                    if ($count > 7) {
                        break;
                    }
                }
                $this->_msgType = 'news';
                return "<ArticleCount>$count</ArticleCount>\r\n<Articles>\r\n$items</Articles>\r\n";
            case 'image':
                return '<Image><MediaId><![CDATA[' . $this->_mediaId . ']]></MediaId></Image>';
                break;
            case 'voice':
                return '<Voice><MediaId><![CDATA[' . $this->_mediaId . ']]></MediaId></Voice>';
                break;
            case 'video':
            case 'music':
            default:
                return '';
        }
    }

    protected function formatNewsItem($item)
    {
        return "<item>\r\n<Title><![CDATA[{$item['title']}]]></Title>\r\n"
        . "<Description><![CDATA[{$item['description']}]]></Description>\r\n"
        . "<PicUrl><![CDATA[{$item['picurl']}]]></PicUrl>\r\n"
        . "<Url><![CDATA[{$item['url']}]]></Url>\r\n</item>\r\n";
    }

    protected function error($msg)
    {
        $this->log($msg);
        header('HTTP/1.1 400 Bad Request');
        echo 'success';
        exit(0);
    }

    protected function checkSignature()
    {
        if (!isset($_GET['signature'], $_GET['timestamp'], $_GET['nonce'])) {
            return false;
        }
        return WechatCrypt::sha1($this->token, $_GET['timestamp'], $_GET['nonce']) === $_GET['signature'];
    }

    /**
     * @return \SimpleXMLElement 解析后的消息数据
     */
    protected function parseIncoming()
    {
        // read msg body
        $msg = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : file_get_contents('php://input');
        $this->debugLog("----------\r\nPOST {$_SERVER['REQUEST_URI']} HTTP/1.1\r\nHost: {$_SERVER['HTTP_HOST']}\r\nUser-Agent: {$_SERVER['HTTP_USER_AGENT']}\r\nContent-Length: " . strlen($msg) . "\r\n\r\n$msg\r\n----------");
        if (isset($_GET['encrypt_type'], $_GET['msg_signature'])) {
            $this->debugLog('check encrypted signature: ' . $_GET['msg_signature']);
            $this->_crypt = new WechatCrypt([
                'token' => $this->token,
                'encodingAesKey' => $this->encodingAesKey,
                'appId' => $this->appId,
            ]);
            $msg = $this->_crypt->decrypt($msg);
            if ($msg === false) {
                $this->error('Decrypt error: ' . $this->_crypt->getError());
            } else {
                $this->debugLog('Decrypted msg: ' . $msg);
            }
        }
        $this->in = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($this->in === false) {
            $this->error('XML parse error');
        }
    }
}

/**
 * Wechat message encrypt and decrpyt
 *
 * ```php
 * $crypt = new WechatCrypt([
 *     'encodingAesKey' => '...',
 *     'token' => '...',
 *     'appId' => '...'
 * ]);
 * $encrypted = $crypt->encrypt($original_msg);
 * $decrypted = $crypt->decrypt($encrypted_msg);
 * ```
 */
class WechatCrypt
{
    const PKCS7_BLOCK_SIZE = 32;

    public $token;
    public $encodingAesKey;
    public $appId;
    private $_error;

    /**
     * 构造函数
     * @param array $config 属性初始化
     */
    public function __construct($config = [])
    {
        foreach ($config as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
    }

    /**
     * 把参数列表排序后连接，用 SHA1 算法生成安全签名
     */
    public static function sha1()
    {
        $args = func_get_args();
        sort($args, SORT_STRING);
        return sha1(implode('', $args));
    }

    /**
     * @return string 操作失败时的错误信息
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * 设置出错信息
     * @param string $error 出错信息
     * @return bool 永远返回 false
     */
    protected function setError($error)
    {
        $this->_error = $error;
        return false;
    }

    /**
     * 将公众平台回复用户的消息加密打包
     * - 对要发送的消息进行 AES-CBC 加密
     * - 生成安全签名
     * - 将消息密文和安全签名打包成 XML 格式
     * @param string $msg 要回复给用户的明文消息，XML 格式
     * @param string $timestamp 时间戳，可以自己生成，也可以用 URL 参数的 timestamp
     * @param string $nonce 随机串，可以自己生成，也可以用 URL 参数的 nonce
     * @return string|bool 成功返回加密后 XML 消息体，出错返回 false
     */
    public function encrypt($msg, $timestamp = null, $nonce = null)
    {
        if (!extension_loaded('mcrypt')) {
            return $this->setError('No mcrypt extension');
        }
        // base64 解码密钥
        $key = base64_decode($this->encodingAesKey . '=');
        $iv = substr($key, 0, 16);
        // 填充16位随机字符串到明文之前
        $text = $this->genRandom16() . pack('N', strlen($msg)) . $msg . $this->appId;
        $text = $this->pkcs7Pad($text);
        // 加密
        $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        mcrypt_generic_init($module, $key, $iv);
        $encrypt = base64_encode(mcrypt_generic($module, $text));
        mcrypt_generic_deinit($module);
        mcrypt_module_close($module);
        // 转换为xml输出
        $timestamp = $timestamp === null && isset($_GET['timestamp']) ? $_GET['timestamp'] : $timestamp;
        $nonce = $nonce === null && isset($_GET['nonce']) ? $_GET['nonce'] : $nonce;
        $signature = self::sha1($this->token, $timestamp, $nonce, $encrypt);
        return $this->formatXml($encrypt, $signature, $timestamp, $nonce);
    }

    /**
     * 检验并解密消息体
     * - 利用收到的密文生成安全签名，进行签名验证
     * - 若验证通过，则提取 XML 中的加密消息
     * - 对消息进行解密
     * @param string $msg 密文，对应 POST 请求的数据
     * @param string $signature 签名串，对应 URL 参数的 msg_signature
     * @param string $timestamp 时间戳 对应URL参数的 timestamp
     * @param string $nonce 随机串，对应URL参数的 nonce
     * @return string|bool 成功返回解密后的原文，失败返回 false
     */
    public function decrypt($msg, $signature = null, $timestamp = null, $nonce = null)
    {
        if (!extension_loaded('mcrypt')) {
            return $this->setError('No mcrypt extension');
        }
        $xml = @simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false || !isset($xml->Encrypt)) {
            return $this->setError('Encrypted message parse error');
        }
        $encrypt = $xml->Encrypt;
        $signature = $signature === null && isset($_GET['msg_signature']) ? $_GET['msg_signature'] : $signature;
        $timestamp = $timestamp === null && isset($_GET['timestamp']) ? $_GET['timestamp'] : $timestamp;
        $nonce = $nonce === null && isset($_GET['nonce']) ? $_GET['nonce'] : $nonce;
        if ($signature !== self::sha1($this->token, $timestamp, $nonce, $encrypt)) {
            return $this->setError('Invalid message signature');
        }
        // 先使用 base64 对密钥和需要解密的字符串进行解码
        $key = base64_decode($this->encodingAesKey . '=');
        $iv = substr($key, 0, 16);
        $ciphertext_dec = base64_decode($encrypt);
        // 解密
        $module = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
        mcrypt_generic_init($module, $key, $iv);
        $decrypted = mdecrypt_generic($module, $ciphertext_dec);
        mcrypt_generic_deinit($module);
        mcrypt_module_close($module);
        // 去除补位字符
        $result = $this->pkcs7Chop($decrypted);
        if (strlen($result) < 20) {
            return $this->setError('Decrypted message length too short');
        }
        $content = substr($result, 16, strlen($result));
        list(, $len) = unpack('N', substr($content, 0, 4));
        $xml = substr($content, 4, $len);
        //$appid = substr($content, $len + 4);
        if ($len !== strlen($xml)) {
            return $this->setError('Decrypted message length is incorrect');
        }
        return $xml;
    }

    /**
     * 格式化 XML 消息
     * @param string $encrypt 消息密文
     * @param string $signature 安全签名
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @return string 加密后的 XML 消息体
     */
    protected function formatXml($encrypt, $signature, $timestamp, $nonce)
    {
        return "<xml>\r\n<Encrypt><![CDATA[{$encrypt}]]></Encrypt>\r\n"
        . "<MsgSignature><![CDATA[{$signature}]]></MsgSignature>\r\n"
        . "<TimeStamp>{$timestamp}</TimeStamp>\r\n"
        . "<Nonce><![CDATA[{$nonce}]]></Nonce>\r\n</xml>";
    }

    /**
     * PKCS7 填充补位
     * @param string $text 加密前的明文
     * @return string
     */
    protected function pkcs7Pad($text)
    {
        $len = strlen($text);
        $pad = self::PKCS7_BLOCK_SIZE - ($len % self::PKCS7_BLOCK_SIZE);
        return $text . str_repeat(chr($pad), $pad);
    }

    /**
     * PKCS7 删除补位
     * @param string $text 解密后的明文
     * @return string
     */
    protected function pkcs7Chop($text)
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > self::PKCS7_BLOCK_SIZE) {
            return $text;
        }
        return substr($text, 0, strlen($text) - $pad);
    }

    /**
     * @return string 随机生成的 16位字符串
     */
    protected function genRandom16()
    {
        $pool = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($pool) - 1;
        $str = '';
        for ($i = 0; $i < 16; $i++) {
            $str .= substr($pool, mt_rand(0, $max), 1);
        }
        return $str;
    }
}
