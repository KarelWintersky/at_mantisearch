<?php

namespace ATFinder\Fetch;

use Arris\Entity\Result;
use ATFinder\DiDomWrapper;
use ATFinder\FetchAbstract;
use Carbon\Carbon;
use DiDom\Element;
use GuzzleHttp\Client;
use RuntimeException;
use Spatie\Regex\Regex;

class FetchTest extends FetchAbstract
{
    public $mapWorkForms = [
        'Роман'                 =>  'Novel',
        'Повесть'               =>  'Tale',
        'Рассказ'               =>  'Story',
        'Сборник рассказов'     =>  'StoryBook',
        'Сборник поэзии'        =>  'Poetry',
        'Перевод'               =>  'Translation'
    ];

    public $mapGenres = [
        "Боевик"                    =>  5,
        'Детектив'                  =>  4,
        'Исторический детектив'     =>  50,
        'Фантастический детектив'   =>  52,
        'Шпионский детектив'        =>  51,
        'Дорама'                    =>  75,
        'Историческая проза'        =>  17,
        'ЛитРПГ'                    =>  20,
        'Любовные романы'           =>  6,
        'Исторический любовный роман'   =>  46,
        'Короткий любовный роман'   =>  45,
        'Современный любовный роман'    =>  67,
        'Мистика'               =>  10,
        'Подростковая проза'    =>  16,
        'Политический роман'    =>  49,
        'Попаданцы'             =>  21,
        'Назад в СССР'          =>  72,
        'Попаданцы в космос'    =>  66,
        'Попаданцы в магические миры'   =>  48,
        'Попаданцы во времени'      =>  47,
        'Поэзия'        =>  13,
        'Приключения'   =>  8,
        'Исторические приключения'  =>  70,
        'Разное'        =>  19,
        'Бизнес-литература' =>  61,
        'Детская литература'    =>  58,
        'Документальная проза'  =>  14,
        'Публицистика'      =>  59,
        'Развитие личности' =>  62,
        'Сказка'        =>  60,
        'РеалРПГ'       =>  69,
        'Современная проза' =>  1,
        'Русреал'   =>  76,
        'Триллер'   =>  11,
        'Ужасы'     =>  18,
        'Фантастика'    =>  3,
        'Альтернативная история'    =>  28,
        'Антиутопия'    =>  29,
        'Боевая фантастика' =>  30,
        'Героическая фантастика'    =>  31,
        'Киберпанк' =>  34,
        'Космическая фантастика'    =>  33,
        'Научная фантастика'    =>  36,
        'Постапокалипсис'   =>  32,
        'Социальная фантастика' =>  63,
        'Стимпанк'  =>  35,
        'Юмористическая фантастика' =>  37,
        'Фанфик'    =>  9,
        'Фэнтези'   =>  2,
        'Боевое фэнтези'    =>  38,
        'Бояръ-Аниме'   =>  71,
        'Бытовое фэнтези'   =>  77,
        'Героическое фэнтези'   =>  64,
        'Городское фэнтези'     =>  39,
        'Историческое фэнтези'  =>  41,
        'Классическое фэнтези'  =>  78,
        'Магическая академия'   =>  74,
        'Романтическое фэнтези' =>  40,
        'Темное фэнтези'    =>  44,
        'Уся'   =>  73,
        'Эпическое фэнтези' =>  43,
        'Юмористическое фэнтези'    =>  42,
        'Эротика'   =>  7,
        'Романтическая эротика' =>  53,
        'Эротическая фантастика'    =>  54,
        'Эротический фанфик'    =>  56,
        'Эротическое фэнтези'   =>  55,
        'Юмор'  =>  12
    ];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Загружает HTML-ку из сети
     *
     * @param $id
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function loadPage($id)
    {
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
        return $request->getBody()->getContents() ?? "";
    }

    /**
     * Сохраняет контент в файл на диск (кэширует)
     *
     * @param $id
     * @param $content
     * @return void
     */
    public function storeFile($id, $content)
    {
        $f = fopen("{$id}.html", "w+");
        fwrite($f, $content, strlen($content));
        fclose($f);
    }

    /**
     * Загружает файл с диска
     *
     * @param $id
     * @return false|string
     */
    public function restoreFile($id)
    {
        $f = fopen("{$id}.html", "r+");
        $content = fread($f, 10_000_000);
        fclose($f);
        return $content;
    }

    public function parse($content)
    {
        $d = new DiDomWrapper($content);

        $data = [
            'work_id'       =>  0,
            'title'         =>  '',
            'annotation'    =>  '',
            'author_notes'  =>  '',
            'cover_url'     =>  '',

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
            'finishTime'    =>  (Carbon::createFromTimestamp(0))->toDateTimeString(),

            'lastUpdateTime'    =>  (Carbon::createFromTimestamp(0))->toDateTimeString(),
            'lastModificationTime'  =>  (Carbon::createFromTimestamp(0))->toDateTimeString(),

            'workForm'      =>  '',

            'genreId'           =>  0,
            'firstSubGenreId'   =>  0,
            'secondSubGenreId'  =>  0,

            'tags'  =>  [],

            'reciter'   =>  '',

            'state'     =>  'Default',
            'format'    =>  'Audiobook',
            'privacyDisplay'    =>  'All'
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
            $data['author_notes'] = trim($d->node('.annotation > div.rich-content:nth-child(2)'));
        }

        if ($d->find('img.cover-image')) {
            $data['cover_url'] = $d->attr('img.cover-image', 'src');
        }

        /*
         * lastUpdateTime - Время последнего обновления книги. Изменение одного из свойств
         * берем из поля "datePublished" в ld+json
         */
        $pattern_last_update_time = <<<PATTERN_LAST_UPDATE
"datePublished":\s+"(?'dt'[\d\-\:TZ\.]+)"
PATTERN_LAST_UPDATE;
        if ( ($r = Regex::match("/{$pattern_last_update_time}/", $content))->hasMatch()) {
            $data['lastUpdateTime'] = Carbon::parse( $r->namedGroup('dt') )->toDateTimeString();
        }

        /*
         * lastModificationTime - Время последнего изменения текста на +15 000 знаков или добавление файла главы.
         * Берем из поля "dateModified" из ld+json
         */
        $pattern_last_modification_time = <<<PATTERN_LAST_MOD
"dateModified":\s+"(?'dt'[\d\-\:TZ\.]+)"
PATTERN_LAST_MOD;
        if ( ($r = Regex::match("/{$pattern_last_modification_time}/", $content))->hasMatch()) {
            $data['lastModificationTime'] = Carbon::parse( $r->namedGroup('dt') )->toDateTimeString();
        }

        /*
         * признак завершения аудиокниги и дата завершения
         */
        $data['isFinished'] = preg_match('/аудиокнига завершена/', $content);
        if ($data['isFinished']) {
            $data['finishTime'] = Carbon::parse(
                $d->attr('div.book-meta-panel span.hint-top[data-time]', 'data-time')
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
            $data['workForm'] = $this->mapWorkForms[ $workForm->text() ];
        }

        /*
         * ЖАНРЫ
         */
        if (array_key_exists(1, $genres)) {
            $genre = $genres[1];
            $data['genreId'] = $this->mapGenres[ $genre->text() ];
        }

        if (array_key_exists(2, $genres)) {
            $genre = $genres[2];
            $data['firstSubGenreId'] = $this->mapGenres[ $genre->text() ];
        }
        if (array_key_exists(3, $genres)) {
            $genre = $genres[3];
            $data['secondSubGenreId'] = $this->mapGenres[ $genre->text() ];
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

}