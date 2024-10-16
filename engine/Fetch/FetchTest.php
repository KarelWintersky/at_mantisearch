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
        return $request->getBody()->getContents() ?? "";
    }

    public function save($id, $content)
    {
        $f = fopen("{$id}.txt", "w+");
        fwrite($f, $content, strlen($content));
        fclose($f);
    }

    public function load($id)
    {
        $f = fopen("{$id}.txt", "r+");
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
        ];

        if ($d->find('.book-meta-panel > div:nth-child(3) > div:nth-child(3) > span:nth-child(3)')) {
            $data += [
                'seriesWorkIds' =>  [],
                'seriesWorkNumber'  =>  str_replace(
                    [' ', '#'],
                    ['', ''],
                    $d->node('.book-meta-panel > div:nth-child(3) > div:nth-child(3) > span:nth-child(3)')
                ),
            ];
        }

        $data += [


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

            'workForm'      =>  '',
            'status'        =>  'Free',

            'authorId'          =>  '',
            'authorFIO'         =>  '',
            'authorUserName'    =>  '',

            'coAuthorId'        =>  '',
            'coAuthorFIO'       =>  '',
            'coAuthorUserName'  =>  '',

            'secondCoAuthorId'  =>  '',
            'secondCoAuthorFIO' =>  '',
            'secondCoAuthorUserName'    =>  '',
            
            'likeCount'         =>  0,
            'commentCount'      =>  0,
            'rewardCount'       =>  0,
            'chapters'  =>  [],
            'freeChapterCount'  =>  0,
            'reviewCount'       =>  0,

            'genreId'   =>  0,
            'firstSubGenreId'   =>  0,
            'secondSubGenreId'      =>  0,

            'tags'  =>  '',

            'state'     =>  '',
            'format'    =>  '',
            'privacyDisplay'    =>  ''


        ];

        dd($data);

        return $data;
    }

}