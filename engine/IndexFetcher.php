<?php

namespace ATFinder;

use AllowDynamicProperties;
use Arris\CLIConsole;
use Arris\Database\DBWrapper;
use Arris\Helpers\CLI;
use Cake\Chronos\Chronos;
use Carbon\Carbon;
use DateTimeInterface;
use GuzzleHttp\Cookie\CookieJar;
use League\Csv\Writer;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;

#[AllowDynamicProperties]
class IndexFetcher extends FetchAbstract
{
    private CookieJar $cookieJar;

    public function __construct()
    {
        parent::__construct();

        $this->cookieJar = CookieJar::fromArray([
            'backend_route' => 'web08'
        ], ".author.today");

        $this->sitemap_urls = [
            'work_tags' =>  "https://author.today/sitemap/work_tags_%s.xml",
            'authors'   =>  "https://author.today/sitemap/authors_%s.xml",
            'posts'     =>  "https://author.today/sitemap/posts_%s.xml",
            'works'     =>  "https://author.today/sitemap/works_%s.xml"
        ];

        $this->offsets = [
            'work_tags' =>  strlen('https://author.today/work/tag/'),
            'authors'   =>  strlen('https://author.today/u/'),
            'posts'     =>  strlen('https://author.today/post/'),
            'works'     =>  strlen('https://author.today/work/')
        ];
    }


    public function loadWorkTags($logging = true): array
    {
        $items = [];
        $items_inner_counter = 1;
        $parts_counter = 1;

        try {
            while (true) {
                $local_tags_found = 0;

                $url = sprintf($this->sitemap_urls['work_tags'], $parts_counter);

                if ($logging) CLIConsole::say("Loading <font color='yellow'>{$url}</font>...");

                $parser = new SitemapParser('MyCustomUserAgent', [
                    'strict' => true,
                    'guzzle' => [
                        'connect_timeout'   =>  10,
                        'cookies'           => $this->cookieJar
                    ],
                ]);
                $parser->parse($url);

                if ($logging) CLIConsole::say("Parsing ...", false);

                foreach ($parser->getURLs() as $record) {
                    $tag = substr($record['loc'], $this->offsets['work_tags']);

                    $items[ $items_inner_counter ] = [
                        'id'    =>  $items_inner_counter,
                        'hash'  =>  md5($tag),
                        'urn'   =>  $tag,
                        'title' =>  urldecode($tag)
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} tags. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("not found, parsing work_tags finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique tags");

        return $items;
    } // loadWorkTags

    public function loadAuthors($logging = true): array
    {
        $items = [];
        $items_inner_counter = 1;
        $parts_counter = 1;

        try {
            while (true) {
                $local_tags_found = 0;

                $url = sprintf($this->sitemap_urls['authors'], $parts_counter);

                if ($logging) CLIConsole::say("Loading <font color='yellow'>{$url}</font>...");

                $parser = new SitemapParser('MyCustomUserAgent', [
                    'strict' => true,
                    'guzzle' => [
                        'connect_timeout'   =>  10,
                        'cookies'           => $this->cookieJar
                    ],
                ]);
                $parser->parse($url);

                if ($logging) CLIConsole::say("Parsing ...", false);

                foreach ($parser->getURLs() as $record) {
                    $author = substr($record['loc'], $this->offsets['authors']);

                    $items[ $items_inner_counter ] = [
                        'id'        =>  $items_inner_counter,
                        'author'    =>  $author,
                        'lastmod'   =>  $record['lastmod'],
                        'lastmod_ts'=>  (new Chronos($record['lastmod']))->timestamp
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} AUTHORS. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("not found, parsing AUTHORS finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique AUTHORS");

        return $items;
    }

    public function loadPosts($logging = true): array
    {
        $items = [];
        $items_inner_counter = 1;
        $parts_counter = 1;

        try {
            while (true) {
                $local_tags_found = 0;

                $url = sprintf($this->sitemap_urls['posts'], $parts_counter);

                if ($logging) CLIConsole::say("Loading <font color='yellow'>{$url}</font>...");

                $parser = new SitemapParser('MyCustomUserAgent', [
                    'strict' => true,
                    'guzzle' => [
                        'connect_timeout'   =>  10,
                        'cookies'           => $this->cookieJar
                    ],
                ]);
                $parser->parse($url);

                if ($logging) CLIConsole::say("Parsing ...", false);

                foreach ($parser->getURLs() as $record) {
                    $id = substr($record['loc'], $this->offsets['posts']);

                    $items[] = [
                        'id'        =>  $id,
                        'lastmod'   =>  $record['lastmod'],
                        'lastmod_ts'=>  (new Chronos($record['lastmod']))->timestamp
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} POSTS. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("not found, parsing POSTS finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique POSTS");

        sort($items);

        return $items;
    }

    public function loadWorks($logging = true): array
    {
        $items = [];
        $items_inner_counter = 1;
        $parts_counter = 1;

        try {
            while (true) {
                $local_tags_found = 0;

                $url = sprintf($this->sitemap_urls['works'], $parts_counter);

                if ($logging) CLIConsole::say("Loading <font color='yellow'>{$url}</font>...");

                $parser = new SitemapParser('MyCustomUserAgent', [
                    'strict' => true,
                    'guzzle' => [
                        'connect_timeout'   =>  10,
                        'cookies'           => $this->cookieJar
                    ],
                ]);
                $parser->parse($url);

                if ($logging) CLIConsole::say("Parsing ...", false);

                foreach ($parser->getURLs() as $record) {
                    $id = substr($record['loc'], $this->offsets['works']);

                    $lm = strtotime($record['lastmod']);
                    $valid_lm = ($lm >= 1) && ($lm <= 2147483647);

                    $items[ $id ] = [
                        'id'        =>  $id,
                        'lastmod'   =>  $valid_lm ? $record['lastmod'] : 0,
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} WORKS. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("WORKS-{$parts_counter} not found, parsing WORKS finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique WORKS");

        sort($items);

        return $items;
    }

    public function updateSQLWorks($new_works):int
    {
        $inserted_rows = 0;
        $total_rows = count($new_works);
        $pad_length = strlen((string)$total_rows) + 2;
        $padded_total = str_pad($total_rows, $pad_length, ' ', STR_PAD_LEFT);

        // получить все имеющиеся работы
        $present_works = $this->loadPresentWorks();

        // установим need_delete для всех present id которых нет в списке works
        if (!empty($new_works)) {
            $sql_set_need_delete = sprintf("UPDATE works SET need_delete = 1 WHERE work_id NOT IN (%s)", implode(',', array_keys($new_works)));
            $this->db->query($sql_set_need_delete);
        }

        CLIConsole::say("Inserting...");

        // проверим данные для обновления
        foreach ($new_works as $work) {
            $work_id = $work['id'];
            // var_dump($work);

            if (array_key_exists($work_id, $present_works)) {
                // книга есть в БД
                $ts_sitemap = Carbon::parse($work['lastmod']);
                $ts_db = Carbon::parse($present_works[$work_id]['latest_fetch']);

                if ($ts_sitemap > $ts_db) {
                    // новый таймштамп книги больше имеющегося, нужно будет обновить данные

                    $sth = $this->db->prepare("UPDATE works SET latest_fetch = :latest_fetch, need_update = 1 WHERE work_id = :work_id");
                    $sth->execute([
                        'latest_fetch'  =>  self::convertDT($work['lastmod']), //@todo: возможно, дату надо как-то обработать
                        // date(DateTimeInterface::ATOM, $work['lastmod'])
                        // или так:
                        // (Carbon::parse($work['lastUpdateTime']))->toDateTimeString(),
                        'work_id'       =>  $work_id
                    ]);
                }
            } else {
                // книги нет в БД, надо вставить первичную запись
                $sth = $this->db->prepare("INSERT INTO works (work_id, latest_fetch, need_update) VALUES (:work_id, :latest_fetch, 1)");
                $sth->execute([
                    'work_id'       =>  $work_id,
                    'latest_fetch'  =>  self::convertDT($work['lastmod'])
                ]);


            }

            $inserted_rows++;
            CLIConsole::say(
                sprintf(
                    "Inserted %s / %s \r",
                    str_pad($inserted_rows, $pad_length, ' ', STR_PAD_LEFT),
                    $padded_total
                ),
                false
            );
        }
        return 1;
    }

    private static function convertDT(string $datetime):string
    {
        $lm = strtotime($datetime);
        $valid_lm = ($lm >= 1) && ($lm <= 2147483647);

        return $valid_lm
            ? Carbon::parse($datetime)->toDateTimeString()
            : Carbon::createFromTimestamp(0)->toDateTimeString();
    }

    private function loadPresentWorks():array
    {
        $sth = $this->db->query("SELECT work_id, latest_fetch, latest_parse FROM works ORDER BY work_id");
        $all_works = [];
        array_map(static function($row) use (&$all_works) {
            $all_works[ $row['work_id'] ] = $row;
        }, $sth->fetchAll());

        return $all_works;
    }

    public function updateSQL($target, $data, $with_author = false):int
    {
        $sth_check = $this->db->prepare("
        SELECT id FROM {$target} WHERE work_id = :work_id
        ");

        $sql = "
REPLACE INTO {$target} (work_id, login, latest_fetch, need_update) 
VALUES (:work_id, :login, :latest_fetch, 1)        
        ";

        $sth = $this->db->prepare($sql);



        $inserted_rows = 0;
        $total_rows = count($data);
        $pad_length = strlen((string)$total_rows) + 2;
        $padded_total = str_pad($total_rows, $pad_length, ' ', STR_PAD_LEFT);
        foreach ($data as $row) {
            $dataset = [
                'work_id'       =>  $row['id'],
                'latest_fetch'  =>  ($row['lastmod'] == 0 ? date(DateTimeInterface::ATOM) : $row['lastmod']),
                'login'         =>  $with_author ? $row['author'] : ''
            ];

            $sth->execute($dataset);
            $inserted_rows++;

            CLIConsole::say(
                sprintf(
                    "Inserted %s / %s \r",
                    str_pad($inserted_rows, $pad_length, ' ', STR_PAD_LEFT),
                    $padded_total
                ),
                false
            );
        }
        CLIConsole::say();

        return $inserted_rows;
    }



}