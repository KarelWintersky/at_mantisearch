<?php

namespace ATFinder;

interface FetchInterface
{
    public function __construct();

    public function run($id = null, $chunk_size = 10, $update_index = true);

}