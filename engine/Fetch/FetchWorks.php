<?php

namespace ATFinder\Fetch;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Query;
use Arris\CLIConsole;
use ATFinder\App;
use ATFinder\FetchAbstract;
use ATFinder\FetchInterface;
use ATFinder\Process\ProcessWorks;
use GuzzleHttp\Exception\GuzzleException;

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
                $this->updateIndexRecord($work_id, 'works');
                continue;
            }

            CLIConsole::say(" ..loading remote data: ", false);

            $work_result = ProcessWorks::getWork($work_id, $this->parse_audiobooks);

            $timer_getWorkDetails = (microtime(true) - $start);
            $timer['getWorkDetails'] += $timer_getWorkDetails;$start = microtime(true);

            if ($work_result->is_error) {
                $this->writeJSON($work_id, $work_result->serialize(), true);

                if ($work_result->getCode() == 404) {
                    $this->markForDelete($work_id, 'works');
                    CLIConsole::say("Work deleted or moved to drafts (marked for delete)");
                    continue;
                }

                if ($work_result->getCode() == 403) {
                    $this->markForDelete($work_id, 'works');
                    CLIConsole::say("Access restricted by author (work marked for delete)");
                    continue;
                }
            }

            if ($work_result->isAudio) {
                if ($this->parse_audiobooks) {
                    $work = ProcessWorks::parseAudioBook($work_id, $work_result);
                } else {
                    CLIConsole::say(" Audiobook unsupported yet");
                    $this->writeJSON($work_id, $work_result->response, prefix: '__');
                    $this->markWorkAsAudiobook($work_id);
                    continue;
                }
            } else {
                $work = ProcessWorks::parseBook($work_id, $work_result);
            }

            if (empty($work)) {
                CLIConsole::say(" other error");
                continue;
            }

            $this->writeJSON($work_id, $work);

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
                    $this->markBrokenWork($work_id, 'works');
                    CLIConsole::say("Work is broken. ", false);
                }
            }

            if ($update_index) {
                $this->updateIndexRecord($work_id, 'works');
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
     * Помечает запись как аудиокнигу
     *
     * @param mixed $id
     * @param string $table
     * @return bool|int|\PDOStatement
     * @throws Exception
     */
    private function markWorkAsAudiobook(mixed $id, string $table = '')
    {
        if (empty($table)) {
            return false;
        }

        return (new Query(App::$PDO))
            ->update($table, [
                'is_audio'      =>  1,
            ])
            ->where("work_id", (int)$id)
            ->execute();
    }






}