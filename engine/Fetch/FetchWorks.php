<?php

namespace ATFinder\Fetch;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Query;
use Arris\CLIConsole;
use ATFinder\App;
use ATFinder\File;
use ATFinder\Process\ProcessWorks;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use vipnytt\SitemapParser;
use vipnytt\SitemapParser\Exceptions\SitemapParserException;

class FetchWorks extends FetchAbstract implements FetchInterface
{
    // "битые" работы, результат парсинга которых не помещается в utf8mb4
    public array $BAD_WORKS_IDS = [
    ];

    private bool $parse_audiobooks;

    public function __construct($parse_audiobooks = false)
    {
        parent::__construct();

        $this->parse_audiobooks = $parse_audiobooks;
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function run($id = null, $chunk_size = 10, $update_index = true)
    {
        if (empty($id)) {
            $ids = $this->getLowestIds('works', 'work_id',   $chunk_size, $this->parse_audiobooks);
        } else {
            $ids = [$id];
        }

        if (empty($ids)) {
            CLIConsole::say('Nothing to do, dataset is empty');
            return 0;
        }

        $fluent = new Query(App::$PDO);

        $current_row = 0;
        $total_rows = count($ids);
        $pad_length = strlen((string)$total_rows) + 2;
        $padded_total = str_pad($total_rows, $pad_length, ' ', STR_PAD_LEFT);

        $timer = [
            'getWorkDetails'    =>  0,
            'writeJSON'         =>  0,
            'makeSQLDataset'    =>  0,
            'fetchID'           =>  0,
            'updateDB'          =>  0,
            'updateStatus'      =>  0
        ];

        foreach ($ids as $work_id) {
            $current_row++;
            $start = $start_task = microtime(true);

            CLIConsole::say(
                sprintf(
                    "[ %s / %s ] Work %s ",
                    str_pad($current_row, $pad_length, ' ', STR_PAD_LEFT),
                    $padded_total,
                    $work_id
                ),
                false
            );

            if (in_array($work_id, $this->BAD_WORKS_IDS)) {
                CLIConsole::say("... is skipped by internal rule");
                $this->setFlag($work_id, 'is_broken', 1);
                continue;
            }

            CLIConsole::say(" ..loading remote data: ", false);

            $work_result = ProcessWorks::getWork($work_id, $this->parse_audiobooks);

            $timer_getWorkDetails = (microtime(true) - $start);
            $timer['getWorkDetails'] += $timer_getWorkDetails;$start = microtime(true);

            if ($work_result->is_error) {
                File::writeJSON($work_id, $work_result->serialize(), true, dir: App::$PROJECT_ROOT . "/json");

                if ($work_result->getCode() == 404) {
                    $this->setFlag($work_id, 'need_delete', 1);
                    CLIConsole::say("Work deleted or moved to drafts (marked for delete)");
                    continue;
                }

                if ($work_result->getCode() == 403) {
                    $this->setFlag($work_id, 'need_delete', 1);
                    CLIConsole::say("Access restricted by author (work marked for delete)");
                    continue;
                }
            }

            if ($work_result->isAudio) {
                if ($this->parse_audiobooks) {
                    $work = ProcessWorks::parseAudioBook($work_id, $work_result);
                } else {
                    CLIConsole::say(" Audiobook unsupported yet");
                    File::writeJSON($work_id, $work_result->response, prefix: '__', dir: App::$PROJECT_ROOT . "/json");
                    $this->setFlag($work_id, 'is_audio', 1);
                    continue;
                }
            } else {
                $work = ProcessWorks::parseBook($work_id, $work_result);
            }

            if (empty($work)) {
                CLIConsole::say(" other error");
                continue;
            }

            File::writeJSON($work_id, $work, dir: App::$PROJECT_ROOT . "/json");

            $timer['writeJSON'] += (microtime(true) - $start);$start = microtime(true);

            $sql_data = ProcessWorks::makeSqlDataset($work_id, $work);

            if (empty($sql_data)) {
                CLIConsole::say(" Skipped due incorrect SQL Data");
            }

            $timer['makeSQLDataset'] += (microtime(true) - $start);$start = microtime(true);

            try {
                $fluent->update('works', $sql_data)->where("work_id = {$work_id}")->execute();

                CLIConsole::say("Database updated. ", false);

                $timer['updateDB'] += (microtime(true) - $start);$start = microtime(true);

            } catch (\Exception $e) {
                if ($e->getCode() == 22007) {
                    // SQLSTATE[22007]: Invalid datetime format: 1366 Incorrect string value
                    $this->setFlag($work_id, 'is_broken', 1);
                    CLIConsole::say("Work is broken. ", false);
                }
            }

            if ($update_index) {
                $this->actualizeItem($work_id, 'works', 'work_id');
            }

            $timer['updateStatus']  += (microtime(true) - $start);$start = microtime(true);

            //$time_taken = number_format(1000 * (microtime(true) - $start_task), 3, '.', ' ');
            $time_taken = number_format(1000*$timer_getWorkDetails, 3, '.', '');

            // CLIConsole::say(" Ok (time taken: {$time_taken}ms)");
            CLIConsole::say("(API response delay: {$time_taken} ms)");
        }

        foreach ($timer as $i => $t) {
            CLIConsole::say(
                sprintf(
                    "<font color='green'>%s</font> taken %s ms",
                    $i,
                    number_format(1000 * $t / $total_rows, 3, '.', ' ')
            ));
        }

        return true;
    }

    /**
     * Устанавливает флаг в нужное значение
     *
     * @throws Exception
     */
    public function setFlag($id, $field, $value)
    {
        if (empty($id) || empty($field)) {
            return false;
        }
        if (empty($value)) {
            $value = 0;
        }

        return (new Query(App::$PDO))
            ->update('works', [
                "{$field}"      =>  $value,
            ])
            ->where("work_id", (int)$id)
            ->execute();

    }

    public function loadSiteMaps($logging = false):array
    {
        $items = [];
        $items_inner_counter = 1;
        $parts_counter = 1;

        try {
            while (true) {
                $local_tags_found = 0;

                $url = sprintf($this->sitemap_urls['works'], $parts_counter);

                if ($logging) CLIConsole::say("Loading <font color='yellow'>{$url}</font>...");

                $parser = new SitemapParser('MyCustomUserAgent', [
                    'strict' => true,
                    'guzzle' => [
                        'connect_timeout'   =>  10,
                        'cookies'           => $this->cookieJar
                    ],
                ]);
                $parser->parse($url);

                if ($logging) CLIConsole::say("Parsing ...", false);

                foreach ($parser->getURLs() as $record) {
                    $id = substr($record['loc'], $this->offsets['works']);

                    $lm = strtotime($record['lastmod']);
                    $valid_lm = ($lm >= 1) && ($lm <= 2147483647);

                    $items[ $id ] = [
                        'id'        =>  $id,
                        'lastmod'   =>  $valid_lm ? $record['lastmod'] : 0,
                    ];

                    $items_inner_counter++;
                    $local_tags_found++;
                }

                if ($logging) CLIConsole::say(" found {$local_tags_found} WORKS. ");

                $parts_counter++;
            }
        } catch (SitemapParserException $e) {
            if ($e->getCode() == 0) {
                if ($logging) CLIConsole::say("WORKS-{$parts_counter} not found, parsing WORKS finished");
            }
        }

        if ($logging) CLIConsole::say("Total found {$items_inner_counter} unique WORKS");

        ksort($items);

        return $items;
    }

    /**
     * Обновляет БД works на основе данных из сайтмэпа
     *
     * @param $new_works
     * @param $logging
     * @return int
     */
    public function updateWorksList($new_works, $logging = true):int
    {
        $inserted_rows = 0;
        $total_rows = count($new_works);
        $pad_length = strlen((string)$total_rows) + 2;
        $padded_total = str_pad($total_rows, $pad_length, ' ', STR_PAD_LEFT);

        // получить все имеющиеся работы
        $present_works = $this->loadPresentWorks();

        // установим need_delete для всех present id которых нет в списке works
        if (!empty($new_works)) {
            $sql_set_need_delete = sprintf("UPDATE works SET need_delete = 1 WHERE work_id NOT IN (%s)", implode(',', array_keys($new_works)));
            $this->db->query($sql_set_need_delete);
        }

        if ($logging) CLIConsole::say("Inserting...");

        // проверим данные для обновления
        foreach ($new_works as $work) {
            $work_id = $work['id'];

            if (array_key_exists($work_id, $present_works)) {
                // книга есть в БД
                $ts_sitemap = Carbon::parse($work['lastmod']);
                $ts_db = Carbon::parse($present_works[$work_id]['latest_fetch']);

                if ($ts_sitemap > $ts_db) {
                    // новый таймштамп книги больше имеющегося, нужно будет обновить данные

                    $sth = $this->db->prepare("UPDATE works SET latest_fetch = :latest_fetch, need_update = 1 WHERE work_id = :work_id");
                    $sth->execute([
                        'latest_fetch'  =>  self::convertDT($work['lastmod']), //@todo: возможно, дату надо как-то обработать
                        // date(DateTimeInterface::ATOM, $work['lastmod'])
                        // или так:
                        // (Carbon::parse($work['lastUpdateTime']))->toDateTimeString(),
                        'work_id'       =>  $work_id
                    ]);
                }
            } else {
                // книги нет в БД, надо вставить первичную запись
                $sth = $this->db->prepare("INSERT INTO works (work_id, latest_fetch, need_update) VALUES (:work_id, :latest_fetch, 1)");
                $sth->execute([
                    'work_id'       =>  $work_id,
                    'latest_fetch'  =>  self::convertDT($work['lastmod'])
                ]);


            }

            $inserted_rows++;
            if ($logging) CLIConsole::say(
                sprintf(
                    "Inserted %s / %s \r",
                    str_pad($inserted_rows, $pad_length, ' ', STR_PAD_LEFT),
                    $padded_total
                ),
                false
            );
        }
        return 1;
    }

    public function loadPresentWorks():array
    {
        $sth = $this->db->query("SELECT work_id, latest_fetch, latest_parse FROM works ORDER BY work_id");
        $all_works = [];
        array_map(static function($row) use (&$all_works) {
            $all_works[ $row['work_id'] ] = $row;
        }, $sth->fetchAll());

        return $all_works;
    }



}