<?php

namespace ATFinder;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use LitEmoji\LitEmoji;
use Normalizer;
use PDO;

class FetchAbstract
{
    /**
     * @var \Arris\Database\DBWrapper
     */
    public mixed $db;

    public function __construct()
    {
        $this->db = App::$PDO;
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

}