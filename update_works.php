<?php

define('ENGINE_START_TIME', microtime(true));
const PATH_ENV = "/etc/arris/at_mantisearch/";
date_default_timezone_set('Europe/Moscow');

use ATFinder\App;
use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createUnsafeImmutable(PATH_ENV, ['common.conf'])->load();

App::factory();

App::$PROJECT_ROOT = __DIR__;

App::$PDO = new \Arris\Database\DBWrapper([
    'driver'            =>  'mysql',
    'database'          =>  getenv('DB.DATABASE'),
    'username'          =>  getenv('DB.USER'),
    'password'          =>  getenv('DB.PASSWORD'),
    'charset'           =>  getenv('DB.CHARSET'),
    'charset_collate'   =>  getenv('DB.COLLATE'),
    'slow_query_threshold'  => 1
]);

$parser = new \ATFinder\Fetch\FetchWorks(true);
$parser->run(null, 100000);

// $parser->run(257399);




