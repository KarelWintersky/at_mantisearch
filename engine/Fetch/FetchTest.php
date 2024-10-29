<?php

namespace ATFinder\Fetch;

use ATFinder\FetchAbstract;
use GuzzleHttp\Client;

class FetchTest extends FetchAbstract
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Загружает HTML-ку из сети
     *
     * @param $id
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
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

    /**
     * Сохраняет контент в файл на диск (кэширует)
     *
     * @param $id
     * @param $content
     * @return void
     */
    public function storeFile($id, $content)
    {
        $f = fopen("{$id}.html", "w+");
        fwrite($f, $content, strlen($content));
        fclose($f);
    }

    /**
     * Загружает файл с диска
     *
     * @param $id
     * @return false|string
     */
    public function restoreFile($id)
    {
        $f = fopen("{$id}.html", "r+");
        $content = fread($f, 10_000_000);
        fclose($f);
        return $content;
    }

}