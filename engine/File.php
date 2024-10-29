<?php

namespace ATFinder;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;

class File
{
    /**
     * Загружает HTML-ку из сети
     *
     * @param $id
     * @return string
     * @throws GuzzleException
     */
    public static function loadHTML(mixed $id):string
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
     * @param string $content
     * @param string $ext
     * @return int
     */
    public static function storeFile($id, string $content = '', string $ext = 'html'): int
    {
        $f = fopen("{$id}.{$ext}", "w+");
        $len = fwrite($f, $content, strlen($content));
        fclose($f);
        return (int)$len;
    }

    /**
     * Загружает файл с диска
     *
     * @param $id
     * @param string $ext
     * @return string
     */
    public static function restoreFile($id, $ext = 'html'):string
    {
        $f = fopen("{$id}.{$ext}", "r+");
        $content = fread($f, 10_000_000);
        fclose($f);
        return $content;
    }

    /**
     * write CSV with data and header
     *
     * @throws CannotInsertRecord
     * @throws \League\Csv\Exception
     */
    public static function writeCSV($target, $header, $data): void
    {
        $csv = Writer::createFromString();
        $csv->insertOne($header);
        $csv->insertAll($data);
        $f = fopen($target, 'w+');
        fwrite($f, $csv->toString());
        fclose($f);
    }

    /**
     * Write JSON to log file
     *
     * @param int $id
     * @param mixed $json
     * @param bool $is_error
     * @param string $prefix
     * @param string $dir
     * @return void
     */
    public static function writeJSON(int $id, mixed $json, bool $is_error = false, string $prefix = '', string $dir = __DIR__): void
    {
        if (!is_string($json)) {
            $json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        // $dir = App::$PROJECT_ROOT . "/json";

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        $_prefix = $is_error ? '_' : '';
        $_prefix = !empty($prefix) ? $prefix : $_prefix;

        $f = fopen("{$dir}/{$_prefix}{$id}.json", "w+");
        fwrite($f, $json, strlen($json));
        fclose($f);
    }



}