<?php

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);
date_default_timezone_set('Europe/Moscow');

use Arris\CLIConsole;
use ATFinder\App;
use ATFinder\File;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

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

$parts = [
    'authors',
    'posts',
    'works',
    'work_tags',
];

// $parser = new \ATFinder\IndexFetcher();

// $tags = $parser->loadWorkTags();
// $parser->writeCSV("_work_tags.csv", ['id', 'hash', 'urn', 'title'], $tags);

// $authors = $parser->loadAuthors();
// $parser->writeCSV("_authors.csv", ['id', 'author', 'lastmod', 'lastmod_ts'], $authors);
// $parser->updateSQL('upd_authors', $authors, true);

// $works = $parser->loadWorks();
// $parser->updateSQLWorks($works);
// File::writeCSV("_works.csv", ['id', 'lastmod', 'lastmod_ts'], $works);

$parser = new \ATFinder\Fetch\FetchWorks();
$works = $parser->loadSiteMaps(true);
$parser->updateWorksList($works);

CLIConsole::say("Writing CSV");
File::writeCSV("_works.csv", ['id', 'lastmod', 'lastmod_ts'], $works);





