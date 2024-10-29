<?php
// создаем первичный индекс для тестирования

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);
date_default_timezone_set('Europe/Moscow');

use \Arris\Toolkit\SphinxQL\PDOWrapper;
use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createUnsafeImmutable(PATH_ENV, ['.config.conf'])->load();

$mysql_connection = new \Arris\Database\DBWrapper([
    'driver'    =>  'mysql',
    'username'  =>  getenv('DB.USER'),
    'password'  =>  getenv('DB.PASSWORD'),
    'database'  =>  getenv('DB.DATABASE'),
    'slow_query_threshold'  => 1
]);

$sphinx_connection = new \Arris\Database\DBWrapper([
    'driver'            =>  'mysql',
    'hostname'          =>  '127.0.0.1',
    'database'          =>  NULL,
    'username'          =>  'root',
    'password'          =>  '',
    'port'              =>  9306,
    'charset'           =>  NULL,
    'charset_collate'   =>  NULL
]);

$toolkit = new PDOWrapper($mysql_connection, $sphinx_connection);
$toolkit->setOptions([
    'log_rows_inside_chunk' =>  false,
    'log_after_chunk'       =>  false,
    'sleep_after_chunk'     =>  0,
    'sleep_time'            =>  0,
    'chunk_length'          =>  1000
]);
$toolkit->setConsoleMessenger([ \Arris\Toolkit\CLIConsole::class, "say" ]);

$rt_index = 'rt_at_works';

$count = $toolkit->rebuildAbstractIndex(
        'works',
        $rt_index,
        static function ($item) {
            return \ATFinder\Search\SearchWorks::prepareRTIndex($item);
        },
        " need_update = 0 AND need_delete = 0 AND is_broken = 0 ",
        true,
        ['genres']
    );
