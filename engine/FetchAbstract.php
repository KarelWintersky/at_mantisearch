<?php

namespace ATFinder;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use PDO;

class FetchAbstract
{

    /**
     * @var \Arris\Database\DBWrapper
     */
    private mixed $db;

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
     * @return array
     */
    public function getLowestIds(string $table = '', string $field = "work_id", int $chunk_size = 1):array
    {
        if (empty($table)) {
            return [];
        }
        // fetch lowest unparsed author id
        $sth = $this->db->query(
            "SELECT {$field} FROM {$table} WHERE latest_parse IS NULL ORDER BY id LIMIT {$chunk_size}"
        );
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
    public function writeJSON(int $id, mixed $json, bool $is_error = false): void
    {
        if (!is_string($json)) {
            $json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $dir = App::$PROJECT_ROOT . "/json";

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }
        $prefix = $is_error ? '_' : '';

        $f = fopen("{$dir}/{$prefix}{$id}.json}", "w+");
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
    public function indexDeleteRecord(mixed $id, string $table_index, string $table_data):bool
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

}