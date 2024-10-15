<?php

namespace ATFinder;

use AllowDynamicProperties;
use Arris\CLIConsole;
use Arris\Database\DBWrapper;
use Arris\Helpers\CLI;
use Cake\Chronos\Chronos;
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

                    $items[ $id ] = [
                        'id'        =>  $id,
                        'lastmod'   =>  $record['lastmod'],
                        'lastmod_ts'=>  (new Chronos($record['lastmod']))->timestamp
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} WORKS. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("not found, parsing WORKS finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique WORKS");

        sort($items);

        return $items;
    }

    public function updateSQL($target, $data, $with_author = false):int
    {
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
                'latest_fetch'  =>  $row['lastmod'],
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