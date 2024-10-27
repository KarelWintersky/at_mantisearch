<?php

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);

use ATFinder\App;
use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createUnsafeImmutable(PATH_ENV, ['.config.conf'])->load();

App::factory();

App::$PROJECT_ROOT = __DIR__;

App::$PDO = new \Arris\Database\DBWrapper([
    'driver'            =>  'mysql',
    'database'          =>  getenv('DB.DATABASE'),
    'username'          =>  getenv('DB.USER'),
    'password'          =>  getenv('DB.PASSWORD'),
    'slow_query_threshold'  => 1
]);

$parser = new \ATFinder\Fetch\FetchWorks(false);
$parser->run(null, 100000);

// $parser->run(256669);
// $parser->run(109778);





