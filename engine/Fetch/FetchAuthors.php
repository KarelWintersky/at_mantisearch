<?php

namespace ATFinder\Fetch;


use Arris\CLIConsole;
use Cake\Chronos\Chronos;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;

class FetchAuthors extends FetchAbstract implements FetchInterface
{
    public function __construct()
    {
        parent::__construct();
    }


    #[\Override]
    public function run($work_id = null, $chunk_size = 10, $update_index = true)
    {
        // TODO: Implement run() method.
    }

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
}