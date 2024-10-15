<?php

namespace ATFinder\Fetch;

use AJUR\FluentPDO\Exception;
use AJUR\FluentPDO\Literal;
use AJUR\FluentPDO\Query;
use Arris\CLIConsole;
use Arris\Entity\Result;
use ATFinder\App;
use ATFinder\FetchAbstract;
use ATFinder\FetchInterface;
use Carbon\Carbon;
use DiDom\Document;
use GuzzleHttp\Client;
use LitEmoji\LitEmoji;
use RuntimeException;
use Spatie\Regex\Regex;

class FetchWorks extends FetchAbstract implements FetchInterface
{
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
            $ids = $this->getLowestIds('index_works', 'work_id',   $chunk_size, $this->parse_audiobooks);
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

            $work_result = $this->getWork($id, $this->parse_audiobooks);

            $timer['getWorkDetails'] += (microtime(true) - $start);$start = microtime(true);

            if ($work_result->is_error) {

                $this->writeJSON($id, $work_result->response, true);

                if ($work_result->getCode() == 404) {
                    $this->indexDeleteRecord($id, 'index_works', 'works');

                    CLIConsole::say(": Work deleted or moved to drafts");

                    continue;
                }
            }

            $work = [];

            if ($work_result->isAudio) {

                if ($this->parse_audiobooks) {
                    $work = $this->parseAudioBook($id, $work_result);
                } else {
                    CLIConsole::say(" Audiobook unsupported yet");
                    $this->writeJSON($id, $work_result->response, prefix: '__');
                    $this->markIndexAsAudiobook($id);
                    continue;
                }
            } else {
                $work = json_decode($work_result->response, true);
            }

            $this->writeJSON($id, $work);

            $timer['writeJSON'] += (microtime(true) - $start);$start = microtime(true);

            $sql_data = $this->makeSqlDatasetAPI($id, $work, (bool)$work_result->isAudio);

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
                $this->updateIndexRecord($id, 'index_works');
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

    public function getWorkDetailsAPI($id):Result
    {
        // https://api.author.today/help/api/get-v1-work-id-details_orderid_orderstatus_recommendationscount

        $r = new Result();
        $r->isAudio = false;
        $r->isHTML = false;
        $r->isJSON = true;

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

            if (
                $response['code'] == 403 &&
                Regex::match("/VersionIsUnsupported/", $response['message'])->hasMatch()
            ) {
                $r->isAudio = true;
            }

        }

        $r->response = $response;

        return $r;
    }

    private function getWorkDetailsRAW($id, $is_audio = false):Result
    {
        $url = $is_audio ? "/audiobook/{$id}" : "/work/{$id}";
        $r = new Result();
        $r->isAudio = $is_audio;
        $response = "";

        try {
            $client = new Client([
                'base_uri'  =>  'https://author.today/'
            ]);
            $request = $client->request(
                'GET',
                $url,
                [
                    'debug'     =>  false,
                    'headers'   =>  [
                        'Authorization' =>  "Bearer guest"
                    ]
                ]
            );
            $response = $request->getBody()->getContents() ?? "";

            $r->html = $response;


        } catch (RuntimeException|\Exception $e) {
            $r->error($e->getMessage());
            $r->setCode($e->getCode());
            $r->setData([
                'code'      =>  $e->getCode(),
                'message'   =>  $e->getMessage()
            ]);
        }

        return $r;
    }



    private function makeSqlDatasetAPI(int $id, array $work, bool $is_audio = false):array
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

    private function markIndexAsAudiobook(mixed $id)
    {
        (new Query(App::$PDO))
            ->update('index_works', [
                'is_audio'      =>  1,
            ])
            ->where("work_id", (int)$id)
            ->execute();
    }

    private function parseAudioBook(mixed $id, Result $work_result)
    {
        return $work_result->response;
    }


}