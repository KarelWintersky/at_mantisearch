<?php

namespace ATFinder\Tasks;

use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use Arris\CLIConsole;
use Arris\Entity\Result;
use Arris\Helpers\CLI;
use Arris\Util\Timer;
use ATFinder\App;
use ATFinder\DataFetcher;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use LitEmoji\LitEmoji;
use RuntimeException;

class FetchWorks
{

    private DataFetcher $fetcher;

    public function __construct()
    {
        $this->fetcher = new DataFetcher();
    }

    public function run($id = null, $chunk_size = 10, $update_index = true)
    {
        if (empty($id)) {
            $ids = $this->fetcher->getLowestIds('index_works', 'work_id',   $chunk_size);
        } else {
            $ids = [$id];
        }

        $fluent = new Query(App::$PDO);

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

        foreach ($ids as $n => $id) {
            $start = $start_task = microtime(true);

            CLIConsole::say(
                sprintf(
                    "[ %s / %s ] Fetching work id %s via API ",
                    str_pad($n, $pad_length, ' ', STR_PAD_LEFT),
                    $padded_total,
                    $id
                ),
                false
            );

            $work_result = $this->getWorkDetails($id);
            $work = (array)$work_result->response;

            $timer['getWorkDetails'] += (microtime(true) - $start);$start = microtime(true);

            if ($work_result->getCode() == 404) {
                $this->indexDeleteRecord($id);
                $this->writeJSON($id, $work);

                CLIConsole::say(": Work deleted or moved to drafts");

                continue;
            }

            if (!empty($work_result->getCode())) {
                CLIConsole::say(" Other API error");

                $this->writeJSON($id, $work);

                continue;
            };

            $this->writeJSON($id, $work);

            $timer['writeJSON'] += (microtime(true) - $start);$start = microtime(true);

            $sql_data = $this->makeSqlDataset($id, $work);

            $timer['makeSQLDataset'] += (microtime(true) - $start);$start = microtime(true);

            $bid = $fluent->from('works', $id)->fetchColumn();
            $timer['fetchID'] += (microtime(true) - $start);$start = microtime(true);

            CLIConsole::say(" updating DB: ", false);

            if (empty($bid)) {
                $fluent->insertInto("works")->values($sql_data)->execute();
                CLIConsole::say("Inserted", false);
            } else {
                $fluent->update('works', $sql_data, $id)->execute();
                CLIConsole::say("Updated", false);
            }

            $timer['updateDB'] += (microtime(true) - $start);$start = microtime(true);

            if ($update_index) {
                $this->updateIndexRecord($id);
            }

            $timer['updateStatus']  += (microtime(true) - $start);$start = microtime(true);

            $time_taken = number_format(1000 * (microtime(true) - $start_task), 3, '.', ' ');

            CLIConsole::say(" Ok (time taken: {$time_taken}ms)");
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

    public function getWorkDetails($id):Result
    {
        // https://api.author.today/help/api/get-v1-work-id-details_orderid_orderstatus_recommendationscount

        $r = new Result();

        try {
            $client = new Client([
                'base_uri'  =>  'https://api.author.today/'
            ]);
            $request = $client->request(
                'GET',
                "v1/work/{$id}/details",
                [
                    'debug'     =>  false,
                    'headers'   =>  [
                        'Authorization' =>  "Bearer guest"
                    ]
                ]
            );
            $response = $request->getBody()->getContents() ?? "{}";

            $response = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        } catch (RuntimeException|\Exception $e) {
            $r->error($e->getMessage());
            $r->setCode($e->getCode());

            $response = [
                'code'      =>  $e->getCode(),
                'message'   =>  $e->getMessage()
            ];

        }

        $r->response = $response;

        return $r;
    }

    private function makeSqlDataset(int $id, array $work):array
    {
        $data = [
            'work_id'       =>  $id,
            'lastmod'       =>  new Literal('NOW()'),
            'title'         =>  LitEmoji::removeEmoji($work['title'] ?? ''),
            'annotation'    =>  LitEmoji::removeEmoji($work['annotation'] ?? ''),
            'author_notes'  =>  LitEmoji::removeEmoji($work['authorNotes'] ?? ''),
            'cover_url'     =>  $work['coverUrl'] ?? '',

            'series_works_ids'  =>  implode(',', $work['seriesWorkIds'] ?? []),
            'series_works_this' =>  $work['seriesWorkNumber'] ?? 0,

            'series_id'     =>  $work['seriesId'] ?? 0,
            'series_order'  =>  $work['seriesOrder'] ?? 0,
            'series_title'  =>  $work['seriesTitle'] ?? '',

            'is_exclusive'      =>  (int)($work['isExclusive'] ?? 'false'),
            'is_promofragment'  =>  (int)($work['promoFragment'] ?? 'false'),
            'is_finished'       =>  (int)($work['isFinished'] ?? 'false'),
            'is_draft'          =>  (int)($work['isDraft'] ?? 'false'),
            'is_adult'          =>  (int)($work['adultOnly'] ?? 'false'),
            'is_adult_pwp'      =>  (int)($work['isPwp'] ?? 'false'),

            'time_last_update'          =>  (Carbon::parse($work['lastUpdateTime']))->toDateTimeString(),
            'time_last_modification'    =>  (Carbon::parse($work['lastModificationTime']))->toDateTimeString(),
            'time_finished'             =>  (Carbon::parse($work['finishTime']))->toDateTimeString(),

            'text_length'       =>  $work['textLength'] ?? 0,
            'price'             =>  $work['price'] ?? 0,

            'work_form'         =>  $work['workForm'] ?? 'Any',
            'work_status'       =>  $work['status'] ?? 'Free',

            'authorId'          =>  LitEmoji::removeEmoji($work['authorId'] ?? $id),
            'authorFIO'         =>  LitEmoji::removeEmoji($work['authorFIO'] ?? ''),
            'authorUserName'    =>  LitEmoji::removeEmoji($work['authorUserName'] ?? $id),

            'coAuthorId'        =>  LitEmoji::removeEmoji($work['coAuthorId'] ?? 0),
            'coAuthorFIO'       =>  LitEmoji::removeEmoji($work['coAuthorFIO'] ?? ''),
            'coAuthorUserName'  =>  LitEmoji::removeEmoji($work['coAuthorUserName'] ?? ''),

            'secondCoAuthorId'  =>  LitEmoji::removeEmoji($work['secondCoAuthorId'] ?? 0),
            'secondCoAuthorFIO' =>  LitEmoji::removeEmoji($work['secondCoAuthorFIO'] ?? ''),
            'secondCoAuthorUserName'    =>  LitEmoji::removeEmoji($work['secondCoAuthorUserName'] ?? ''),

            'count_like'        =>  $work['likeCount'] ?? 0,
            'count_comments'    =>  $work['commentCount'] ?? 0,
            'count_rewards'     =>  $work['rewardCount'] ?? 0,
            'count_chapters'        =>  count($work['chapters'] ?? [1]),
            'count_chapters_free'   =>  $work['freeChapterCount'] ?? 1,
            'count_review'      =>  $work['reviewCount'] ?? 0,

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

            'tags'          =>  '',
            'tags_text'     =>  LitEmoji::removeEmoji(implode(',', $work['tags'] ?? [])),

            'entity_state'      =>  $work['state'] ?? 'Default',
            'entity_format'     =>  $work['format'] ?? 'Any',
            'entity_privacy'    =>  $work['privacyDisplay'] ?? 'All'
        ];

        return $data;
    }

    /**
     * Write JSON to log file
     *
     * @param mixed $json
     * @return void
     */
    private function writeJSON(int $id, mixed $json)
    {
        if (!is_string($json)) {
            $json = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        $dir = App::$PROJECT_ROOT . "/json";

        if (!is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $f = fopen("{$dir}/{$id}.json", "w+");
        fwrite($f, $json, strlen($json));
        fclose($f);
    }

    /**
     * Удаляет запись из индексной таблицы и таблицы works
     *
     * @param mixed $id
     * @return bool
     * @throws \AJUR\FluentPDO\Exception
     */
    private function indexDeleteRecord(mixed $id)
    {
        return
        (new Query(App::$PDO))->delete("index_works")->where('work_id', (int)$id)->execute()
        &&
        (new Query(App::$PDO))->delete('works')->where('work_id', $id)->execute();
    }


    /**
     * Обновляет статус записи в индексной таблице
     *
     * @param mixed $id
     * @return bool|int|\PDOStatement
     * @throws \AJUR\FluentPDO\Exception
     */
    private function updateIndexRecord(mixed $id)
    {
        return
            (new Query(App::$PDO))
            ->update("index_works", [
                'latest_parse'  =>  new Literal("latest_fetch"),
                'need_update'   =>  0
            ])
            ->where("work_id", (int)$id)
            ->execute();
        // var_dump($f->getQuery(true), $f->getParameters());
    }


}