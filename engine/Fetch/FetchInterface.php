<?php

namespace ATFinder\Fetch;

interface FetchInterface
{
    public function __construct();

    public function run($work_id = null, $chunk_size = 10, $update_index = true);

}