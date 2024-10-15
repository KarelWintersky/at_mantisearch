<?php

namespace ATFinder\Fetch;

use Arris\Entity\Result;
use ATFinder\DiDomWrapper;
use ATFinder\FetchAbstract;
use GuzzleHttp\Client;
use RuntimeException;

class FetchTest extends FetchAbstract
{
    public function __construct()
    {
        parent::__construct();
    }

    public function loadPage($id)
    {
        $r = new Result();

        $client_raw = new Client([
            'base_uri'  =>  'https://author.today/'
        ]);
        $request = $client_raw->request(
            'GET',
            "/audiobook/{$id}",
            [
                'debug'     =>  false,
                'headers'   =>  [
                    'Authorization' =>  "Bearer guest"
                ]
            ]
        );
        $response = $request->getBody()->getContents() ?? "";
        $r->setMessage($response);

        return $r;
    }

    public function save($id, $content)
    {
        $f = fopen("{$id}.txt", "w+");
        fwrite($f, $content->getMessage(), strlen($content->getMessage()));
        fclose($f);
    }

    public function load($id)
    {
        $f = fopen("{$id}.txt", "w+");
        $content = fread($f, 10_000_000);
        fclose($f);
        return $content;
    }

    public function parse($content)
    {
        $d = new DiDomWrapper($content);

        $data = [
            'title'         =>  $d->node('.book-title > span[itemprop="name"]'),
            'annotation'    =>  $d->node('.annotation > div.rich-content:nth-child(1)'),
            'author_notes'  =>  $d->node('.annotation > div.rich-content:nth-child(2)'),
            'cover_url'     =>  $d->attr('img.cover-image', 'src'),

            'seriesWorkIds' =>  [],
            'seriesWorkNumber'  =>  str_replace(
                [' ', '#'],
                ['', ''],
                $d->node('.book-meta-panel > div:nth-child(3) > div:nth-child(3) > span:nth-child(3)')
            ),

            'seriesId'      =>  0,
            'seriesOrder'   =>  0,
            'seriesTitle'   =>  '',

            'isExclusive'   =>  '',
            'promoFragment' =>  '',
            'isFinished'    =>  '',
            'adultOnly'     =>  '',

            'lastUpdateTime'    =>  '',
            'lastModificationTime'  =>  '',
            'finishTime'        =>  '',

            'textLength'    =>  0,
            'price'         =>  0,

        ];

        dd($data);

        return $data;
    }

}