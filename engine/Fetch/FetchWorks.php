<?php

namespace ATFinder\Fetch;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use Arris\CLIConsole;
use Arris\Entity\Result;
use ATFinder\App;
use ATFinder\DiDomWrapper;
use ATFinder\FetchAbstract;
use ATFinder\FetchInterface;
use ATFinder\Process\ProcessWorks;
use Carbon\Carbon;
use DiDom\Document;
use DiDom\Element;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LitEmoji\LitEmoji;
use Normalizer;
use RuntimeException;
use Spatie\Regex\Regex;

class FetchWorks extends FetchAbstract implements FetchInterface
{
    // "битые" работы, результат парсинга которых не помещается в utf8mb4
    public array $BAD_WORKS_IDS = [
    ];

    private bool $parse_audiobooks = false;

    /**
     * Timezone
     * @var string
     */
    private string $timezone;

    public function __construct($parse_audiobooks = false)
    {
        parent::__construct();

        $this->parse_audiobooks = $parse_audiobooks;
        $this->timezone = 'Europe/Moscow';
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

            $sql_data = $this->makeSqlDataset($work_id, $work);

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
    }


    private function makeSqlDataset(int $id, array $work):array
    {
        $data = [
            'work_id'           =>  $id,
            'latest_parse'      =>  new Literal('NOW()'),

            'need_update'       =>  0,

            'work_form'         =>  $work['workForm'] ?? 'Any',
            'work_status'       =>  $work['status'] ?? 'Free',
            'work_state'        =>  $work['state'] ?? 'Default',
            'work_format'       =>  $work['format'] ?? 'Any',
            'work_privacy'      =>  $work['privacyDisplay'] ?? 'All',

            'is_audio'          =>  (int)$work['isAudio'],
            'is_exclusive'      =>  (int)($work['isExclusive'] ?? 'false'),
            'is_promofragment'  =>  (int)($work['promoFragment'] ?? 'false'),
            'is_finished'       =>  (int)($work['isFinished'] ?? 'false'),
            'is_draft'          =>  (int)($work['isDraft'] ?? 'false'),
            'is_adult'          =>  (int)($work['adultOnly'] ?? 'false'),
            'is_adult_pwp'      =>  (int)($work['isPwp'] ?? 'false'),

            'count_like'        =>  $work['likeCount'] ?? 0,
            'count_comments'    =>  $work['commentCount'] ?? 0,
            'count_rewards'     =>  $work['rewardCount'] ?? 0,
            'count_chapters'        =>  count($work['chapters'] ?? [1]),
            'count_chapters_free'   =>  $work['freeChapterCount'] ?? 1,
            'count_review'      =>  $work['reviewCount'] ?? 0,

            'time_last_update'          =>  (Carbon::parse($work['lastUpdateTime'], $this->timezone))->toDateTimeString(),
            'time_last_modification'    =>  (Carbon::parse($work['lastModificationTime'], $this->timezone))->toDateTimeString(),
            'time_finished'             =>  (Carbon::parse($work['finishTime'], $this->timezone))->toDateTimeString(),

            'text_length'       =>  $work['textLength'] ?? 0,
            'audio_length'      =>  $work['audioLength'] ?? 0,

            'price'             =>  $work['price'] ?? 0,

            'title'         =>  $work['title'] ?? '',
            'annotation'    =>  $work['annotation'] ?? '',
            'author_notes'  =>  $work['authorNotes'] ?? '',
            'cover_url'     =>  $work['coverUrl'] ?? '',

            'series_id'     =>  $work['seriesId'] ?? 0,
            'series_order'  =>  $work['seriesOrder'] ?? 0,
            'series_title'  =>  $work['seriesTitle'] ?? '',

            'tags'          =>  '',
            'tags_text'     =>  implode(',', $work['tags'] ?? []),

            'authorId'          =>  $work['authorId'] ?? $id,
            'authorFIO'         =>  $work['authorFIO'] ?? '',
            'authorUserName'    =>  $work['authorUserName'] ?? $id,

            'coAuthorId'        =>  $work['coAuthorId'] ?? 0,
            'coAuthorFIO'       =>  $work['coAuthorFIO'] ?? '',
            'coAuthorUserName'  =>  $work['coAuthorUserName'] ?? '',

            'secondCoAuthorId'  =>  $work['secondCoAuthorId'] ?? 0,
            'secondCoAuthorFIO' =>  $work['secondCoAuthorFIO'] ?? '',
            'secondCoAuthorUserName'    =>  $work['secondCoAuthorUserName'] ?? '',

            'genre_main'    =>  $work['genreId'] ?? 0,
            'genre_2nd'     =>  $work['firstSubGenreId'] ?? 0,
            'genre_3rd'     =>  $work['secondSubGenreId'] ?? 0,
            'genres'        =>  (function($work){
                $genres = [];
                if (array_key_exists('genreId', $work) && !is_null($work['genreId'])) {
                    $genres[] = $work['genreId'];
                }
                if (array_key_exists('firstSubGenreId', $work) && !is_null($work['firstSubGenreId'])) {
                    $genres[] = $work['firstSubGenreId'];
                }
                if (array_key_exists('secondSubGenreId', $work) && !is_null($work['secondSubGenreId'])) {
                    $genres[] = $work['secondSubGenreId'];
                }
                return implode(',', $genres);
            })($work),
        ];

        // remove emoji
        // https://packagist.org/packages/wikimedia/utfnormal (to NFKC - выяснено экспериментально)
        // https://habr.com/ru/articles/45489/
        // или
        // https://www.php.net/manual/en/class.normalizer.php

        foreach (['title', 'annotation', 'author_notes', 'authorFIO', 'coAuthorFIO', 'secondCoAuthorFIO', 'series_title', 'tags_text'] as $key) {
            $data[$key] = FetchAbstract::sanitize($data[$key]);
        }

        // https://dencode.com/string/unicode-normalization


        return $data;
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