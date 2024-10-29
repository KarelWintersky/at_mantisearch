<?php

use ATFinder\App;
use Dotenv\Dotenv;

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);
date_default_timezone_set('Europe/Moscow');

require_once __DIR__ . '/vendor/autoload.php';

// Dotenv::create( PATH_ENV, '.config.conf' )->load();

Dotenv::createUnsafeImmutable(PATH_ENV, ['.config.conf'])->load();

App::$PDO = new \Arris\Database\DBWrapper([
    'driver'            =>  'mysql',
    'database'          =>  getenv('DB.DATABASE'),
    'username'          =>  getenv('DB.USER'),
    'password'          =>  getenv('DB.PASSWORD'),
    'slow_query_threshold'  => 1
]);

$p = new \ATFinder\Fetch\FetchTest();
$id = 311474;

if (!file_exists(__DIR__ . "audio.php")) {
    $content = $p->loadPage($id);
    $p->storeFile($id, $content);
} else {
    $content = $p->restoreFile($id);
}

$data = $p->parse($content);

var_dump($data);

