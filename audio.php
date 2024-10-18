<?php

use ATFinder\App;
use Dotenv\Dotenv;

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);

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
$id = 256669;

// $content = $p->loadPage($id);
// $p->save($id, $content);

$content = $p->load($id);
$p->parse($content);

