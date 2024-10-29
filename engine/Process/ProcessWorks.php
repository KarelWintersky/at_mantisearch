<?php

namespace ATFinder\Process;

use AJUR\FluentPDO\Literal;
use Arris\Entity\Result;
use ATFinder\DiDomWrapper;
use ATFinder\Fetch\FetchAbstract;
use ATFinder\Mapping;
use Carbon\Carbon;
use DiDom\Element;
use GuzzleHttp\Client;
use RuntimeException;
use Spatie\Regex\Regex;

class ProcessWorks
{
    public static string $timezone = 'Europe/Moscow';

    /**
     * Загружает JSON-контент или текстовый контент, если это аудиокнига
     *
     * @param $id
     * @param $fetch_audio
     * @return Result
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getWork($id, $fetch_audio = false):Result
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

            if ($e->getCode() == 403) {

                // check audio
                if (Regex::match("/VersionIsUnsupported/", $e->getMessage())->hasMatch()) {
                    $r->success("Audiobook found, not supported by API");
                    $r->isAudio = true;
                }

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
                        'User-Agent'    =>  'okhttp/4.12.0 X_AT_CONTENT'
                        // 'Authorization' =>  "Bearer guest"
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

    /**
     * Парсит HTML-контент страницы аудиокниги
     *
     * @param mixed $id
     * @param Result $work_result
     * @return array
     * @throws \DiDom\Exceptions\InvalidSelectorException
     * @throws \Spatie\Regex\Exceptions\RegexFailed
     */
    public static function parseAudioBook(mixed $id, Result $work_result)
    {
        $content = $work_result->response;

        $d = new DiDomWrapper($content);

        $data = [
            'work_id'       =>  $id,
            'title'         =>  '',
            'annotation'    =>  '',
            'authorNotes'   =>  '',
            'coverUrl'      =>  '',

            'price'         =>  0,
            'status'        =>  'Free',

            'textLength'    =>  0,
            'audioLength'   =>  0,

            'likeCount'         =>  0,
            'commentCount'      =>  0, // узнать невозможно, кол-во догружается аяксом
            'rewardCount'       =>  $d->find('.panel .panel-heading a:nth-child(4)') ? $d->node('.panel .panel-heading a:nth-child(4)') : 0,
            'chapters'          =>  [],
            'freeChapterCount'  =>  0,
            'reviewCount'       =>  0,  // узнать невозможно, кол-во догружается аяксом

            'seriesId'      =>  0,
            'seriesOrder'   =>  0,
            'seriesTitle'   =>  '',

            'isExclusive'   =>  '',
            'promoFragment' =>  '',
            'adultOnly'     =>  '',


            'isFinished'    =>  0,
            'finishTime'    =>  (Carbon::createFromTimestamp(0, self::$timezone))->toDateTimeString(),

            'lastUpdateTime'    =>  (Carbon::createFromTimestamp(0, self::$timezone))->toDateTimeString(),
            'lastModificationTime'  =>  (Carbon::createFromTimestamp(0, self::$timezone))->toDateTimeString(),

            'workForm'      =>  '',

            'genreId'           =>  0,
            'firstSubGenreId'   =>  0,
            'secondSubGenreId'  =>  0,

            'tags'  =>  [],

            'reciter'   =>  '',

            'state'     =>  'Default',
            'format'    =>  'Audiobook',
            'privacyDisplay'    =>  'All',
            'isAudio'   =>  1,
        ];

        /*
         * название, аннотация, авторские примечания
         */
        if ($d->find('.book-title > span[itemprop="name"]')) {
            $data['title'] = trim($d->node('.book-title > span[itemprop="name"]'));
        }
        if ($d->find('.annotation > div.rich-content:nth-child(1)')) {
            $data['annotation'] = trim($d->node('.annotation > div.rich-content:nth-child(1)'));
        }
        if ($d->find('.annotation > div.rich-content:nth-child(2)')) {
            $data['authorNotes'] = trim($d->node('.annotation > div.rich-content:nth-child(2)'));
        }

        if ($d->find('img.cover-image')) {
            $data['coverUrl'] = $d->attr('img.cover-image', 'src');
        }

        /*
         * lastUpdateTime - Время последнего обновления книги. Изменение одного из свойств
         * берем из поля "datePublished" в ld+json
         */
        $pattern_last_update_time = <<<PATTERN_LAST_UPDATE
"datePublished":\s+"(?'dt'[\d\-\:TZ\.]+)"
PATTERN_LAST_UPDATE;
        if ( ($r = Regex::match("/{$pattern_last_update_time}/", $content))->hasMatch()) {
            $data['lastUpdateTime'] = Carbon::parse( $r->namedGroup('dt'), self::$timezone)->toDateTimeString();
        }

        /*
         * lastModificationTime - Время последнего изменения текста на +15 000 знаков или добавление файла главы.
         * Берем из поля "dateModified" из ld+json
         */
        $pattern_last_modification_time = <<<PATTERN_LAST_MOD
"dateModified":\s+"(?'dt'[\d\-\:TZ\.]+)"
PATTERN_LAST_MOD;
        if ( ($r = Regex::match("/{$pattern_last_modification_time}/", $content))->hasMatch()) {
            $data['lastModificationTime'] = Carbon::parse( $r->namedGroup('dt'), self::$timezone)->toDateTimeString();
        }

        /*
         * признак завершения аудиокниги и дата завершения
         */
        $data['isFinished'] = preg_match('/аудиокнига завершена/', $content);
        if ($data['isFinished']) {
            $data['finishTime'] = Carbon::parse(
                $d->attr('div.book-meta-panel span.hint-top[data-time]', 'data-time'),
                self::$timezone
            )->toDateTimeString();
        }

        /**
         * Информация о цене
         *
         * @var Element $buy_button
         */
        // $buy_button = $d->find("buy-button");
        if (!empty($d->find("buy-button")) && $buy_button_params = $d->attr("buy-button", 'params')) {
            $buy_button_params = json_decode($buy_button_params, true);
            $data['price'] = $buy_button_params['price'];
            $data['status'] = $buy_button_params['workStatus'];
        }

        /*
         * Собираем информацию об авторах из двух источников
         *
         * Информация с hide-button нам дает информации и о серии (возможной)
         * */
        $authors_ids = [];
        if (!empty($d->find('hide-button')) && $hide_button_params = $d->attr('hide-button', 'params')) {
            $hide_button_params = json_decode($hide_button_params, true);
            $authors_ids = array_flip($hide_button_params['authors']);

            if (
                array_key_exists('seriesTitle', $hide_button_params)
                &&
                array_key_exists('seriesId', $hide_button_params)
            ) {
                $data['seriesTitle'] = $hide_button_params['seriesTitle'];
                $data['seriesId'] = $hide_button_params['seriesId'];

                $pattern = <<<SERIES_ORDER_PATTERN
<a href="\/work\/series\/\d+">.+<\/a><span>&nbsp;#(?'seriesOrder'\d+)<\/span>
SERIES_ORDER_PATTERN;
                if ( ($r = Regex::match("/{$pattern}/", $content))->hasMatch()) {
                    $data['seriesOrder'] = (int)$r->namedGroup('seriesOrder');
                }
            } // series info
        }

        $authors = [];
        if ($nodes = $d->find('div.book-authors span[itemprop="author"] a')) {
            foreach ($nodes as $node) {
                $link = $node->attr("href");
                $r = Regex::match('/\/u\/(\w+)\/works.+/', $link);
                if ($r->hasMatch()) {
                    $login = $r->group(1);
                }
                $fio = $node->text();
                $authors[] = [
                    'id'        =>  array_key_exists($fio, $authors_ids) ? $authors_ids[$fio] : 0,
                    'login'     =>  $login,
                    'fio'       =>  FetchAbstract::sanitize($fio)
                ];
            }
        }
        if (array_key_exists(0, $authors)) {
            $data += [
                'authorId'          =>  $authors[0]['id'],
                'authorFIO'         =>  $authors[0]['fio'],
                'authorUserName'    =>  $authors[0]['login'],
            ];
        }

        if (array_key_exists(1, $authors)) {
            $data += [
                'coAuthorId'          =>  $authors[1]['id'],
                'coAuthorFIO'         =>  $authors[1]['fio'],
                'coAuthorUserName'    =>  $authors[1]['login'],
            ];
        }

        if (array_key_exists(2, $authors)) {
            $data += [
                'secondCoAuthorId'          =>  $authors[2]['id'],
                'secondCoAuthorFIO'         =>  $authors[2]['fio'],
                'secondCoAuthorUserName'    =>  $authors[2]['login'],
            ];
        }

        /**
         * количество лайков
         * */
        if (($r = Regex::match('/likeCount:\s(\d+),/', $content))->hasMatch()) {
            $data['likeCount'] = $r->group(1);
        }

        /*
         * форма произведения
         */
        $genres = $d->find('div.book-genres a');

        if (array_key_exists(0, $genres)) {
            $workForm = $genres[0];
            $data['workForm'] = Mapping::$map_WorkForms[ $workForm->text() ];
        }

        /*
         * ЖАНРЫ
         */
        if (array_key_exists(1, $genres)) {
            $genre = $genres[1];
            $data['genreId'] = Mapping::$map_GenreToID[ $genre->text() ];
        }

        if (array_key_exists(2, $genres)) {
            $genre = $genres[2];
            $data['firstSubGenreId'] = Mapping::$map_GenreToID[ $genre->text() ];
        }
        if (array_key_exists(3, $genres)) {
            $genre = $genres[3];
            $data['secondSubGenreId'] = Mapping::$map_GenreToID[ $genre->text() ];
        }

        /*
         * Число глав и число доступных глав
         */
        $data['freeChapterCount'] = count($d->find("div.audio-chapters div.chapter-available"));
        // $data['chapters']   = $d->find("div.audio-chapters div.chapter");
        $data['chapters'] = array_fill(0, count($d->find("div.audio-chapters div.chapter")), 'chapter');

        /*
         * теги
         */
        $tags = [];
        if (!empty($tags_collection = $d->find("span.tags > a"))) {
            foreach ($tags_collection as $tag) {
                $tags[] = $tag->attr("title");
            }
        }
        $data['tags'] = $tags;

        /*
         * длительность звучания в секундах
         */
        $audio_length = 0;
        $audio_length_pattern = "((?'hours'\d+)\s+ч\.)?\s+((?'minutes'\d+)\s+мин\.)\s+((?'seconds'\d+)\s+сек\.)?";
        if ( ($r = Regex::match("/((?'hours'\d+)\sч\.)/", $content))->hasMatch()) {
            $audio_length += 3600 * (int)$r->namedGroup('hours');
        }

        if ( ($r = Regex::match("/((?'minutes'\d+)\sмин\.)/", $content))->hasMatch()) {
            $audio_length += 60 * (int)$r->namedGroup('minutes');
        }

        if ( ($r = Regex::match("/((?'seconds'\d+)\sсек\.)/", $content))->hasMatch()) {
            $audio_length += 60 * (int)$r->namedGroup('seconds');
        }
        $data['audioLength'] = $audio_length;

        /*
         * Adult Only or not
         */
        if ( ($r = Regex::match("/adultOnly:\s(?'adultOnly'true|false)/", $content))->hasMatch() ) {
            $adult_flag = $r->namedGroup('adultOnly');
            $data['adultOnly'] = match ($adult_flag) {
                'true'  =>  1,
                default =>  0
            };
        }

        /*
         * Reciters - чтецы
         */
        $reciters = [];
        if ($_reciters = $d->find('div.book-meta-panel > div:nth-child(3) > div:nth-child(4) a')) {
            foreach ($_reciters as $r) {
                $reciters[] = $r->text();
            }
        }
        // $data['reciter'] = json_encode($reciters)
        $data['reciter'] = implode(' ', $reciters);
        // а ссылка с чтецов такая:
        // <a href="/search?category=works&amp;q=%D0%95%D0%BB%D0%B5%D0%BD%D0%B0%20%D0%9F%D0%BE%D1%80%D0%BE%D1%88%D0%B8%D0%BD%D0%B0&amp;field=reciter">Елена Порошина</a>

        /**
         * Флаг "эксклюзив"
         */
        if ( (Regex::match("/\<span\>Эксклюзив\<\/span\>/", $content))->hasMatch()) {
            $data['isExclusive'] = 1;
        }

        /**
         * Флаг "ознакомительный фрагмент"
         */
        if ( $d->find('span.book-promo-label') ||
            (Regex::match("/ознакомительный фрагмент\<\/span\>/", $content))->hasMatch()
        ) {
            $data['promoFragment'] = 1;
        }

        return $data;
    }

    /**
     * "Парсит" JSON-ответ API для обычной книги
     *
     * @param mixed $id
     * @param Result $work_result
     * @return mixed
     */
    public static function parseBook(mixed $id, Result $work_result)
    {
        $data = json_decode($work_result->response, true);
        $data['isAudio'] = 0;
        return $data;
    }

    /**
     * Билдит SQL-датасет
     *
     * @param int $id
     * @param array $work
     * @return array
     */
    public static function makeSqlDataset(int $id, array $work):array
    {
        $data = [
            'work_id'               =>  $id,
            'latest_parse'          =>  new Literal('NOW()'),

            'need_update'           =>  0,

            'work_form'             =>  $work['workForm'] ?? 'Any',
            'work_status'           =>  $work['status'] ?? 'Free',
            'work_state'            =>  $work['state'] ?? 'Default',
            'work_format'           =>  $work['format'] ?? 'Any',
            'work_privacy'          =>  $work['privacyDisplay'] ?? 'All',

            'is_audio'              =>  (int)$work['isAudio'],
            'is_exclusive'          =>  (int)($work['isExclusive'] ?? 'false'),
            'is_promofragment'      =>  (int)($work['promoFragment'] ?? 'false'),
            'is_finished'           =>  (int)($work['isFinished'] ?? 'false'),
            'is_draft'              =>  (int)($work['isDraft'] ?? 'false'),
            'is_adult'              =>  (int)($work['adultOnly'] ?? 'false'),
            'is_adult_pwp'          =>  (int)($work['isPwp'] ?? 'false'),

            'count_like'            =>  $work['likeCount'] ?? 0,
            'count_comments'        =>  $work['commentCount'] ?? 0,
            'count_rewards'         =>  $work['rewardCount'] ?? 0,
            'count_chapters'        =>  count($work['chapters'] ?? [1]),
            'count_chapters_free'   =>  $work['freeChapterCount'] ?? 1,
            'count_review'          =>  $work['reviewCount'] ?? 0,

            'time_last_update'      =>  (Carbon::parse($work['lastUpdateTime'], self::$timezone))->toDateTimeString(),
            'time_last_modification'=>  (Carbon::parse($work['lastModificationTime'], self::$timezone))->toDateTimeString(),
            'time_finished'         =>  (Carbon::parse($work['finishTime'], self::$timezone))->toDateTimeString(),

            'text_length'           =>  $work['textLength'] ?? 0,
            'audio_length'          =>  $work['audioLength'] ?? 0,

            'price'                 =>  $work['price'] ?? 0,

            'title'                 =>  $work['title'] ?? '',
            'annotation'            =>  $work['annotation'] ?? '',
            'author_notes'          =>  $work['authorNotes'] ?? '',
            'cover_url'             =>  $work['coverUrl'] ?? '',

            'series_id'             =>  $work['seriesId'] ?? 0,
            'series_order'          =>  $work['seriesOrder'] ?? 0,
            'series_title'          =>  $work['seriesTitle'] ?? '',

            'tags'                  =>  '',
            'tags_text'             =>  implode(',', $work['tags'] ?? []),

            'authorId'              =>  $work['authorId'] ?? $id,
            'authorFIO'             =>  $work['authorFIO'] ?? '',
            'authorUserName'        =>  $work['authorUserName'] ?? $id,

            'coAuthorId'            =>  $work['coAuthorId'] ?? 0,
            'coAuthorFIO'           =>  $work['coAuthorFIO'] ?? '',
            'coAuthorUserName'      =>  $work['coAuthorUserName'] ?? '',

            'secondCoAuthorId'      =>  $work['secondCoAuthorId'] ?? 0,
            'secondCoAuthorFIO'     =>  $work['secondCoAuthorFIO'] ?? '',
            'secondCoAuthorUserName'=>  $work['secondCoAuthorUserName'] ?? '',

            'genre_main'            =>  $work['genreId'] ?? 0,
            'genre_2nd'             =>  $work['firstSubGenreId'] ?? 0,
            'genre_3rd'             =>  $work['secondSubGenreId'] ?? 0,
            'genres'                =>  (function($work){
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

            'reciter'               =>  $work['reciter']
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




}