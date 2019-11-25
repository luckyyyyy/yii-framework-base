<?php
/**
 * Simple test codes
 * @author hightman
 */

namespace app\documer;

spl_autoload_register(function ($name) {
    $pos = strrpos($name, '\\');
    if ($pos !== false) {
        $name = substr($name, $pos + 1);
    }
    require_once $name . '.php';
});

function ptime()
{
    static $last = null;
    $now = microtime(true);
    if ($last !== null) {
        printf("%.4fs\n", $now - $last);
    }
    $last = $now;
}

ptime();

echo 'Creating objects ... ';
$obj = new Documer();
ptime();

echo 'Traning ... ';
$obj->train('en', 'This is a pen.');
$obj->train('en', 'He is my friend.');
$obj->train('ja', 'Kore ha pen desu.');
$obj->train('ja', 'Kare ha watasi no tomodati desu.');
$obj->train('cn', 'Zhe shi qian bi.');
$obj->train('cn', 'Ta shi wo de peng you.');
$tests = [
    'This is a test.',
    'ha wo desu.',
    'wo de qian bi ne?',
    'whoi is your friend?',
];
ptime();

echo '---- TEST BEGIN ----', PHP_EOL;
foreach ($tests as $test) {
    $res = $obj->guess($test);
    $top = key($res);
    echo sprintf('[%s %d%%] ', $top, $res[$top] * 100), $test, PHP_EOL;
}
echo '---- TEST END ----', PHP_EOL;
