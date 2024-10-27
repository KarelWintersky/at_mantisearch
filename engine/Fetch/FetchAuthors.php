<?php

namespace ATFinder\Fetch;


use ATFinder\FetchAbstract;
use ATFinder\FetchInterface;

class FetchAuthors extends FetchAbstract implements FetchInterface
{
    public function __construct()
    {
        parent::__construct();
    }


    #[\Override]
    public function run($work_id = null, $chunk_size = 10, $update_index = true)
    {
        // TODO: Implement run() method.
    }
}