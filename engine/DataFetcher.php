<?php

namespace ATFinder;

use Arris\Database\DBWrapper;
use PDO;

class DataFetcher
{
    private DBWrapper $db;

    public function __construct()
    {
        $this->db = App::$PDO;
    }

    /**
     * get lowest unparsed author ids
     *
     * @param string $table
     * @param int $chunk_size
     * @return array
     */
    public function getLowestIds($table = '', $field = "work_id", $chunk_size = 1):array
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


}