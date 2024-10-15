<?php

namespace ATFinder\Tasks;

class FetchAuthors
{
    private \Arris\Database\DBWrapper $db;

    public function __construct(\Arris\Database\DBWrapper $db)
    {
        $this->db = $db;
    }

    public function run()
    {
        // fetch lowest unparsed author id
        $sth = $this->db->query(
            "SELECT MIN(id) FROM upd_authors WHERE latest_parse IS NULL"
        );
        $id = $sth->fetchColumn();

        var_dump($id);
    }

}