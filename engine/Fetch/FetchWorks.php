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
use Carbon\Carbon;
use DiDom\Document;
use GuzzleHttp\Client;
use LitEmoji\LitEmoji;
use Normalizer;
use RuntimeException;
use Spatie\Regex\Regex;

class FetchWorks extends FetchAbstract implements FetchInterface
{
    // "битые" работы, результат парсинга которых не помещается в utf8mb4
    public array $BAD_WORKS_IDS = [
        // 79508, 79777, 80125, 81575, 109778
    ];

    private bool $parse_audiobooks = false;
    public function __construct($parse_audiobooks = false)
    {
        parent::__construct();

        $this->parse_audiobooks = $parse_audiobooks;
    }

    /**
     * @throws Exception
     */
    public function run($id = null, $chunk_size = 10, $update_index = true)
    {
        if (empty($id)) {
            $ids = $this->getLowestIds('works', 'work_id',   $chunk_size, $this->parse_audiobooks);
        } else {
            $ids = [$id];
        }

        $fluent = new Query(App::$PDO);

        $current_row = 1;
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

            $work_result = $this->getWork($work_id, $this->parse_audiobooks);

            $timer_getWorkDetails = (microtime(true) - $start);
            $timer['getWorkDetails'] += $timer_getWorkDetails;$start = microtime(true);

            if ($work_result->is_error) {

                $this->writeJSON($work_id, $work_result->response, true);

                if ($work_result->getCode() == 404) {
                    // $this->indexDeleteRecord($id, 'works');
                    $this->markForDelete($work_id, 'works');
                    CLIConsole::say("Work deleted or moved to drafts");
                    continue;
                }
            }

            $work = [];

            if ($work_result->isAudio) {

                if ($this->parse_audiobooks) {
                    $work = $this->parseAudioBook($work_id, $work_result);
                } else {
                    CLIConsole::say(" Audiobook unsupported yet");
                    $this->writeJSON($work_id, $work_result->response, prefix: '__');
                    $this->markWorkAsAudiobook($work_id);
                    continue;
                }
            } else {
                $work = $this->parseBook($work_id, $work_result);
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

            /*
            $bid = $fluent->from('works')->where("work_id", $work_id)->fetchColumn();
            $timer['fetchID'] += (microtime(true) - $start);$start = microtime(true);

            if (empty($bid)) {
                $fluent->insertInto("works")->values($sql_data)->execute();
                CLIConsole::say("Inserted", false);
            } else {
                $fluent->update('works', $sql_data, $work_id)->execute();
                CLIConsole::say("Updated", false);
            }*/

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

            $current_row++;
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

    public function getWork($id, $fetch_audio = false):Result
    {
        $r = new Result();
        $r->isAudio = false;
        $r->isJSON = true;
        $r->isHTML = false;
        $r->response = "{}";

        try {
            $client_api = new Client([
                'base_uri'  =>  'https://api.author.today/'
            ]);
            $request = $client_api->request(
                'GET',
                "v1/work/{$id}/details",
                [
                    'debug'     =>  false,
                    'headers'   =>  [
                        'Authorization' =>  "Bearer guest"
                    ]
                ]
            );
            $r->response = $request->getBody()->getContents() ?? "{}";

            return $r;

        } catch (RuntimeException|\Exception $e) {
            $r->error($e->getMessage());
            $r->setCode($e->getCode());

            if (
                $e->getCode() == 403 &&
                Regex::match("/VersionIsUnsupported/", $e->getMessage())->hasMatch()
            ) {
                $r->success("Is Error");
                $r->isAudio = true;
            } elseif (Regex::match("/cURL error/", $e->getMessage())->hasMatch()) {
                $r->setCode(35);
                return $r;
            } else {
                return $r;
            }
        }

        if ($fetch_audio === false) {
            return $r;
        }

        try {
            $client_raw = new Client([
                'base_uri'  =>  'https://author.today/'
            ]);
            $request = $client_raw->request(
                'GET',
                "/audiobook/{$id}",
                [
                    'debug'     =>  false,
                    'headers'   =>  [
                        'Authorization' =>  "Bearer guest"
                    ]
                ]
            );
            $r->response = $response = $request->getBody()->getContents() ?? "{}";
            $r->isHTML = true;
            $r->isJSON = false;
        } catch (RuntimeException|\Exception $e) {
            $r->error($e->getMessage());
            $r->setCode($e->getCode());
    }

        return $r;
    }

    private function makeSqlDataset(int $id, array $work, bool $is_audio = false):array
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

            'is_audio'          =>  $is_audio ? 1 : 0,
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

            'time_last_update'          =>  (Carbon::parse($work['lastUpdateTime']))->toDateTimeString(),
            'time_last_modification'    =>  (Carbon::parse($work['lastModificationTime']))->toDateTimeString(),
            'time_finished'             =>  (Carbon::parse($work['finishTime']))->toDateTimeString(),

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
            $string = $data[$key];

            $string = trim($string);
            $string = (new Normalizer())->normalize($string, Normalizer::NFKC);
            $string = LitEmoji::removeEmoji($string);

            $data[$key] = $string;
        }

        // https://dencode.com/string/unicode-normalization


        return $data;
    }

    private function markWorkAsAudiobook(mixed $id, string $table = '')
    {
        if (empty($table)) {
            return false;
        }

        (new Query(App::$PDO))
            ->update($table, [
                'is_audio'      =>  1,
            ])
            ->where("work_id", (int)$id)
            ->execute();
    }

    public function parseAudioBook(mixed $id, Result $work_result)
    {
        $d = new DiDomWrapper($work_result->response);

        $data = [
            'title'         =>  $d->node('.book-title > span[itemprop="name"]'),
            'annotation'    =>  $d->node('.annotation > div.rich-content:nth-child(1)'),
            'author_notes'  =>  $d->node('.annotation > div.rich-content:nth-child(2)'),
            'cover_url'     =>  $d->attr('img.cover-image', 'src'),

            'seriesWorkIds' =>  [],
            'seriesWorkNumber'  =>  str_replace(
                [' ', '#'],
                ['', ''],
                $d->node('.book-meta-panel > div:nth-child(3) > div:nth-child(3) > span:nth-child(3)')
            ),

            'seriesId'      =>  0,
            'seriesOrder'   =>  0,
            'seriesTitle'   =>  '',

            'isExclusive'   =>  '',
            'promoFragment' =>  '',
            'isFinished'    =>  '',
            'adultOnly'     =>  '',

            'lastUpdateTime'    =>  '',
            'lastModificationTime'  =>  '',
            'finishTime'        =>  '',

            'textLength'    =>  0,
            'price'         =>  0,

        ];

        dd($data);

        return $data;
    }

    private function parseBook(mixed $id, Result $work_result)
    {
        return json_decode($work_result->response, true);
    }




}