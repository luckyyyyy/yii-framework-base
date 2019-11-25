<?php
/**
 * This file is part of the yii-framework-base.
 * @author William Chan <root@williamchan.me>
 *
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 * WARNING: DO NOT MODIFY
 */
namespace app\commands;

use hightman\http\Client;
use hightman\http\Request;
use Yii;

/**
 * 命令控制器基类
 * 统一添加控制台日志输出机制，设置内存上限等，增加选项 verbose。
 * @author hightman <hightman@cloud-sun.com>
 * @author William Chan <root@williamchan.me>
 */
abstract class Controller extends \yii\console\Controller
{
    /**
     * @var bool 是否需要更详细输出日志信息
     */
    public $verbose = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        Yii::$app->log->targets['console'] = Yii::createObject([
            'class' => ConsoleLog::class,
            'levels' => ['error', 'warning', 'info'],
            'exportInterval' => 1,
            'logVars' => [],
        ]);
        Yii::$app->log->flushInterval = 1;
        ini_set('memory_limit', '8192M');
    }

    /**
     * 选项处理 verbose
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if ($this->verbose) {
            Yii::$app->log->targets['console']->setLevels(['error', 'warning', 'info', 'trace']);
        }
        return parent::beforeAction($action);
    }

    /**
     * 添加全局选项 verbose 和 help
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        $options[] = 'verbose';
        return $options;
    }

    /**
     * 帮助信息
     */
    public function actionIndex()
    {
        Yii::$app->runAction('help', [$_SERVER['argv'][1]]);
    }

    /**
     * 单例运行判断
     * @param string $suffix
     * @param string $route 检查其它 route
     * @return int 正在运行的返回 pid, 未运行返回 0, 出错返回 -1
     */
    protected function processId($suffix = null, $route = null)
    {
        static $fds = [];
        $lockFile = Yii::getAlias('@app/runtime') . '/' . strtr($route ? $route : $this->uniqueId, '/', '-');
        if ($suffix !== null) {
            $lockFile .= '_' . $suffix;
        }
        $lockFile .= '.lock';
        if (($fd = @fopen($lockFile, 'c+')) === false) {
            return -1;
        } elseif (!flock($fd, LOCK_EX | LOCK_NB)) {
            $pid = (int) fgets($fd, 256);
            fclose($fd);
            return $pid;
        }
        if ($route === null) {
            fwrite($fd, getmypid());
            fflush($fd);
            $fds[] = $fd;
        } else {
            fclose($fd);
        }
        return 0;
    }

    /**
     * 批量发送模板消息
     * @param \app\models\wechat\User[] $all
     * @param string $template
     * @param array $data
     * @param string $url
     * @param int $batch
     * @param Closure $cb
     */
    protected function batchTemplate($all, $template, $data, $url, $batch = 100, $cb = null)
    {
        $wechat = Yii::$app->get('wechatApp');
        echo '[BATCH TEMPLATE] begin count=', count($all), ', now=', date('Y/m/d H:i:s'), ' ...', PHP_EOL;
        $timeBegin0 = microtime(true);
        // first via wechat
        echo '[BATCH TEMPLATE] first via wechatApp component ...', PHP_EOL;
        // try get template alias
        if (isset($wechat->templates[$template])) {
            $template = $wechat->templates[$template];
        }
        $wechat->sendTemplate($all[0]['openid'], $url, $template, $data);
        // others use httpclient
        $last = count($all) - 1;
        $ret = [];
        $mhCallback = function ($result, $key) use (&$ret, $cb, $wechat) {
            echo "\t[", $key, "]: ", $result, PHP_EOL;
            // unsubcribed user: errcode=43004
            $error = json_decode($result, true);
            if ($error && isset($error['errcode'])) {
                if (!isset($ret[(string) $error['errcode']])) {
                    $ret[(string) $error['errcode']] = 1;
                } else {
                    $ret[(string) $error['errcode']]++;
                }
                if ($error['errcode'] == 40001 || $error['errcode'] == 42001) {
                    $wechat->getAppToken(true);
                }
                if ($cb instanceof \Closure) {
                    $cb($error);
                }
            }
        };
        $mh = curl_multi_init();
        $reqs = [];
        $access_token = urlencode($wechat->getAppToken());
        for ($i = 1; $i <= $last; $i++) {
            $api = $wechat->apiBaseUrl . 'message/template/send?access_token=' . $access_token;
            $body = json_encode([
                'touser' => $all[$i]['openid'],
                'template_id' => $template,
                'url' => $url,
                'data' => $data,
            ], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($api);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Connection: close',
            ]);
            $reqs[] = $ch;
            curl_multi_add_handle($mh, $ch);
            if ($i === $last || ($i % $batch) === 0) {
                $timeBegin1 = microtime(true);
                echo '[BATCH TEMPLATE] point begin #', $i, ', count=', count($reqs), '...', PHP_EOL;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running > 0);
                echo '[BATCH TEMPLATE] point end #', $i, ', time=', sprintf('%.4f', microtime(true) - $timeBegin1), PHP_EOL;
                for ($req = 0; $req < count($reqs); $req++) {
                    $result = curl_multi_getcontent($reqs[$req]);
                    $mhCallback($result, $req);
                    curl_multi_remove_handle($mh, $reqs[$req]);
                }
                $reqs = [];
                // refresh
                $access_token = urlencode($wechat->getAppToken());
            }
        }
        curl_multi_close($mh);
        foreach ($ret as $k => $v) {
            echo '[BATCH TEMPLATE] retcode=', $k , ', count=' , $v, PHP_EOL;
        }
        echo '[BATCH TEMPLATE] end time=', sprintf('%.4f', microtime(true) - $timeBegin0), PHP_EOL;
    }

    /**
     * 批量发送客服消息
     * @param \app\models\wechat\User[] $all
     * @param array $data
     * @param array $msgType
     * @param int $batch
     * @param Closure $cb
     */
    protected function batchCustomer($all, $data, $msgType, $batch = 100, $cb = null)
    {
        $wechat = Yii::$app->get('wechatApp');
        echo '[BATCH CUSTOMER] begin count=', count($all), ', now=', date('Y/m/d H:i:s'), ' ...', PHP_EOL;
        $timeBegin0 = microtime(true);
        // first via wechat
        echo '[BATCH CUSTOMER] first via wechatApp component ...', PHP_EOL;
        $wechat->sendCustomer($all[0]['openid'], $data, $msgType);
        // others use httpclient
        $last = count($all) - 1;
        $ret = [];
        $mhCallback = function ($result, $key) use (&$ret, $cb, $wechat) {
            echo "\t[", $key, "]: ", $result, PHP_EOL;
            // unsubcribed user: errcode=43004
            $error = json_decode($result, true);
            if ($error && isset($error['errcode'])) {
                if (!isset($ret[(string) $error['errcode']])) {
                    $ret[(string) $error['errcode']] = 1;
                } else {
                    $ret[(string) $error['errcode']]++;
                }
                if ($error['errcode'] == 40001 || $error['errcode'] == 42001) {
                    $wechat->getAppToken(true);
                }
                if ($cb instanceof \Closure) {
                    $cb($error);
                }
            }
        };
        $mh = curl_multi_init();
        $reqs = [];
        $access_token = urlencode($wechat->getAppToken());
        for ($i = 1; $i <= $last; $i++) {
            $api = $wechat->apiBaseUrl . 'message/custom/send?access_token=' . $access_token;
            $body = json_encode([
                'touser' => $all[$i]['openid'],
                'msgtype' => $msgType,
                $msgType => $data,
            ], JSON_UNESCAPED_UNICODE);
            $ch = curl_init($api);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Connection: close',
            ]);
            $reqs[] = $ch;
            curl_multi_add_handle($mh, $ch);
            if ($i === $last || ($i % $batch) === 0) {
                $timeBegin1 = microtime(true);
                echo '[BATCH CUSTOMER] point begin #', $i, ', count=', count($reqs), '...', PHP_EOL;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running > 0);
                echo '[BATCH CUSTOMER] point end #', $i, ', time=', sprintf('%.4f', microtime(true) - $timeBegin1), PHP_EOL;
                for ($req = 0; $req < count($reqs); $req++) {
                    $result = curl_multi_getcontent($reqs[$req]);
                    $mhCallback($result, $req);
                    curl_multi_remove_handle($mh, $reqs[$req]);
                }
                $reqs = [];
                // refresh
                $access_token = urlencode($wechat->getAppToken());
            }
        }
        foreach ($ret as $k => $v) {
            echo '[BATCH CUSTOMER] retcode=', $k , ', count=' , $v, PHP_EOL;
        }
        echo '[BATCH CUSTOMER] end time=', sprintf('%.4f', microtime(true) - $timeBegin0), PHP_EOL;
    }
}
