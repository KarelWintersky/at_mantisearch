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




}