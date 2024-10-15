<?php

namespace ATFinder\Fetch;

use ATFinder\DataFetcher;

class FetchAuthors
{
    private \Arris\Database\DBWrapper $db;
    private DataFetcher $fetcher;

    public function __construct()
    {
        $this->fetcher = new DataFetcher();
    }

    public function run()
    {
    }

}