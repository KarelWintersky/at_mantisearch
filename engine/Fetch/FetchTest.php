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
        'Повесть'               =>  'Tale', // Требуется уточнение!!!
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

    public function save($id, $content)
    {
        $f = fopen("{$id}.html", "w+");
        fwrite($f, $content, strlen($content));
        fclose($f);
    }

    public function load($id)
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
            'title'         =>  trim($d->node('.book-title > span[itemprop="name"]')),
            'annotation'    =>  trim($d->node('.annotation > div.rich-content:nth-child(1)')),
            'author_notes'  =>  trim($d->node('.annotation > div.rich-content:nth-child(2)')),
            'cover_url'     =>  $d->attr('img.cover-image', 'src'),

            'price'         =>  0,
            'status'        =>  'Free',

            'textLength'    =>  0,
            'audioLength'   =>  0,

            'likeCount'         =>  0,
            'commentCount'      =>  0, /*$d->node('#commentTotalCount')*/ // узнать невозможно, кол-во догружается аяксом
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
            'finishTime'    =>  (Carbon::createFromTime())->toDateTimeString(),

            'lastUpdateTime'    =>  '',
            'lastModificationTime'  =>  '',

            'workForm'      =>  '',

            'genreId'           =>  0,
            'firstSubGenreId'   =>  0,
            'secondSubGenreId'  =>  0,

            'tags'  =>  '',

            'state'     =>  '',
            'format'    =>  '',
            'privacyDisplay'    =>  ''
        ];

        // признак завершения аудиокниги и дата завершения
        $data['isFinished'] = preg_match('/аудиокнига завершена/', $content);
        if ($data['isFinished']) {
            $data['finishTime'] = Carbon::parse(
                $d->attr('div.book-meta-panel span.hint-top[data-time]', 'data-time')
            )->toDateTimeString();
        } else {
            // ?
            // 'lastUpdateTime'    =>  '',
            // 'lastModificationTime'  =>  '',
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

        // компилируем информацию об авторах

        /*
         * Собираем информацию об авторах из двух источников
         * */
        $authors_ids = [];
        if (!empty($d->find('hide-button')) && $hide_button_params = $d->attr('hide-button', 'params')) {
            $hide_button_params = json_decode($hide_button_params, true);
            $authors_ids = array_flip($hide_button_params['authors']);
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

        // число лайков
        if (($r = Regex::match('/likeCount:\s(\d+),/', $content))->hasMatch()) {
            $data['likeCount'] = $r->group(1);
        }

        // форма произведения
        $genres = $d->find('div.book-genres a');

        if (array_key_exists(0, $genres)) {
            $workForm = $genres[0];
            $data['workForm'] = $this->mapWorkForms[ $workForm->text() ];
        }

        // жанры
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

        // $d->find('.book-meta-panel > div:nth-child(3) > div:nth-child(3) > span:nth-child(3)')

        dd($data);

        return $data;
    }

}