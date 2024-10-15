<?php

define('ENGINE_START_TIME', microtime(true));
define('PATH_ENV', __DIR__);

use Arris\CLIConsole;
use Dotenv\Dotenv;
use League\Csv\Writer;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;
use GuzzleHttp\Cookie\CookieJar;

require __DIR__ . '/vendor/autoload.php';

Dotenv::create( PATH_ENV, '.config.conf' )->load();

$parts = [
    'authors',
    'posts',
    'works',
    'work_tags',
];

CLIConsole::say("Writing CSV");

$parser = new \ATFinder\IndexFetcher();
$parser->setPDOHandler(new \Arris\Database\DBWrapper([
    'driver'            =>  'mysql',
    'database'          =>  getenv('DB.DATABASE'),
    'username'          =>  getenv('DB.USER'),
    'password'          =>  getenv('DB.PASSWORD'),
    'slow_query_threshold'  => 1
]));


// $tags = $parser->loadWorkTags();
// $parser->writeCSV("_work_tags.csv", ['id', 'hash', 'urn', 'title'], $tags);
//
//$authors = $parser->loadAuthors();
//$parser->writeCSV("_authors.csv", ['id', 'author', 'lastmod', 'lastmod_ts'], $authors);
//$parser->updateSQL('upd_authors', $authors, true);

$works = $parser->loadWorks();
$parser->writeCSV("_works.csv", ['id', 'lastmod', 'lastmod_ts'], $works);
$parser->updateSQL("index_works", $works);

