<?php

namespace ATFinder\Fetch;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use AllowDynamicProperties;
use Arris\CLIConsole;
use Arris\Database\DBWrapper;
use ATFinder\App;
use Carbon\Carbon;
use DateTimeInterface;
use GuzzleHttp\Cookie\CookieJar;
use LitEmoji\LitEmoji;
use Normalizer;
use PDO;

#[AllowDynamicProperties]
class FetchAbstract
{
    /**
     * @var DBWrapper
     */
    public mixed $db;

    public CookieJar $cookieJar;

    public function __construct()
    {
        $this->db = App::$PDO;

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

        $this->cookieJar = CookieJar::fromArray([
            'backend_route' => 'web08'
        ], ".author.today");
    }

    /**
     * get lowest unparsed author ids
     *
     * @param string $table
     * @param string $field
     * @param int $chunk_size
     * @param bool $parse_audiobooks
     * @return array
     */
    public function getLowestIds(string $table = '', string $field = "work_id", int $chunk_size = 1, bool $parse_audiobooks = false):array
    {
        if (empty($table)) {
            return [];
        }

        $sql
            = $parse_audiobooks
            ? "SELECT {$field} FROM {$table} WHERE need_delete = 0 AND need_update = 1 AND is_broken = 0                  AND latest_parse IS NULL ORDER BY id LIMIT {$chunk_size}"
            : "SELECT {$field} FROM {$table} WHERE need_delete = 0 AND need_update = 1 AND is_broken = 0 AND is_audio = 0 AND latest_parse IS NULL ORDER BY id LIMIT {$chunk_size}"
        ;

        $sth = $this->db->query($sql);
        return $sth->fetchAll(PDO::FETCH_COLUMN, 0) ?? [];
    }

    /**
     * Удаляет элемент из таблицы
     *
     * @param mixed $id
     * @param string $table
     * @param string $index_field
     * @return bool
     * @throws Exception
     */
    public function deleteItem(mixed $id, string $table, string $index_field):bool
    {
        if (empty($id) || empty($table) || empty($index_field)) {
            return false;
        }

        return (new Query(App::$PDO))->delete($table)->where($index_field, (int)$id)->execute();
    }

    /**
     * Актуализирует запись (ставит дату парсинга = дате скачивания инфы из сайтмапа и ставит флаг need_update = 0)
     *
     * @param mixed $id
     * @param string $table
     * @param $index_field
     * @return bool
     * @throws Exception
     */
    public function actualizeItem(mixed $id, string $table, $index_field = 'work_id'):bool
    {
        if (empty($id) || empty($table_index)) {
            return false;
        }

        return
            (new Query(App::$PDO))
                ->update($table, [
                    'latest_parse'  =>  new Literal("latest_fetch"),
                    'need_update'   =>  0
                ])
                ->where("work_id", (int)$id)
                ->execute();
    }

    /**
     * Санитайз строки
     *
     * @param string $string
     * @return string
     */
    public static function sanitize(string $string):string
    {
        $string = trim($string);
        $string = (new Normalizer())->normalize($string, Normalizer::NFKC);
        $string = LitEmoji::removeEmoji($string);
        return $string;
    }

    public static function convertDT(string $datetime):string
    {
        $lm = strtotime($datetime);
        $valid_lm = ($lm >= 1) && ($lm <= 2147483647);

        return $valid_lm
            ? Carbon::parse($datetime)->toDateTimeString()
            : Carbon::createFromTimestamp(0)->toDateTimeString();
    }

    /**
     * @deprecated
     *
     * @param $target
     * @param $data
     * @param $with_author
     * @return int
     */
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