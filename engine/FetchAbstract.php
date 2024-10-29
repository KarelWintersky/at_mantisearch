<?php

namespace ATFinder;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use League\Csv\CannotInsertRecord;
use League\Csv\Writer;
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
     * Write JSON to log file
     *
     * @param int $id
     * @param mixed $json
     * @param bool $is_error
     * @return void
     */
    public function writeJSON(int $id, mixed $json, bool $is_error = false, string $prefix = ''): void
    {
        if (!is_string($json)) {
            $json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $dir = App::$PROJECT_ROOT . "/json";

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        $_prefix = $is_error ? '_' : '';
        $_prefix = !empty($prefix) ? $prefix : $_prefix;

        $f = fopen("{$dir}/{$_prefix}{$id}.json", "w+");
        fwrite($f, $json, strlen($json));
        fclose($f);
    }

    /**
     * Удаляет запись из индексной таблицы и таблицы works
     *
     * @param mixed $id
     * @param string $table_index
     * @param string $table_data
     * @return bool
     * @throws Exception
     */
    public function indexDeleteRecord(mixed $id, string $table_index = '', string $table_data = ''):bool
    {
        if (empty($id)) {
            return false;
        }

        $success = true;

        if (!empty($table_index)) {
            $success &= (new Query(App::$PDO))->delete($table_index)->where('work_id', (int)$id)->execute();
        }

        if (!empty($table_data)) {
            $success &= (new Query(App::$PDO))->delete($table_data)->where('work_id', $id)->execute();
        }

        return $success;
    }

    public function markForDelete(mixed $id, string $table = ''):bool
    {
        if (empty($id) || empty($table)) {
            return false;
        }

        return (new Query(App::$PDO))->update($table, [
            'need_delete'   =>  1,
        ])->where('work_id', (int)$id)->execute();
    }

    /**
     * Помечает работу как "сломанную"
     *
     * @param mixed $id
     * @param string $table
     * @return bool
     * @throws Exception
     */
    public function markBrokenWork(mixed $id, string $table = ''):bool
    {
        if (empty($id) || empty($table)) {
            return false;
        }

        return (new Query(App::$PDO))->update($table, [
            'is_broken'     =>  1,
        ])->where('work_id', (int)$id)->execute();
    }

    /**
     * Обновляет статус записи в индексной таблице
     *
     * @param mixed $id
     * @param string $table_index
     * @return bool
     * @throws Exception
     */
    public function updateIndexRecord(mixed $id, string $table_index): bool
    {
        if (empty($id) || empty($table_index)) {
            return false;
        }

        return
            (new Query(App::$PDO))
                ->update($table_index, [
                    'latest_parse'  =>  new Literal("latest_fetch"),
                    'need_update'   =>  0
                ])
                ->where("work_id", (int)$id)
                ->execute();
        // var_dump($f->getQuery(true), $f->getParameters());
    }


    /**
     * write CSV with data and header
     *
     * @throws CannotInsertRecord
     * @throws \League\Csv\Exception
     */
    public function writeCSV($target, $header, $data): void
    {
        $csv = Writer::createFromString();
        $csv->insertOne($header);
        $csv->insertAll($data);
        $f = fopen($target, 'w+');
        fwrite($f, $csv->toString());
        fclose($f);
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