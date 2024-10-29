<?php

namespace ATFinder\Search;

class SearchWorks
{
    public static function prepareRTIndex($item)
    {
        $dataset = [
            'title'         =>  $item['title'],
            'annotation'    =>  $item['annotation'],
            'author_notes'  =>  $item['author_notes'],
            'series_title'  =>  $item['series_title'],
            'tags_text'     =>  $item['tags_text'],
            'authorFIO'     =>  $item['authorFIO'],
            'coAuthorFIO'   =>  $item['coAuthorFIO'],
            'secondCoAuthorFIO' =>  $item['secondCoAuthorFIO'],
            'reciter'       =>  $item['reciter'],

            'cover_url'     =>  $item['cover_url'],
            'work_form'     =>  $item['work_form'],
            'work_status'   =>  $item['work_status'],
            'authorUserName'    =>  $item['authorUserName'],
            'coAuthorUserName'  =>  $item['coAuthorUserName'],
            'secondCoAuthorUserName'    =>  $item['secondCoAuthorUserName'],

            'work_id'       =>  $item['work_id'],
            'count_like'    =>  $item['count_like'],
            'count_comments'    =>  $item['count_comments'],
            'count_rewards'     =>  $item['count_rewards'],
            'count_chapters'    =>  $item['count_chapters'],
            'count_chapters_free'   =>  $item['count_chapters_free'],
            'count_review'  =>  $item['count_review'],
            'text_length'   =>  $item['text_length'],
            'audio_length'  =>  $item['audio_length'],
            'price'     =>  $item['price'],
            'series_id' =>  $item['series_id'],
            'series_order'  =>  $item['series_order'],
            'authorId'      =>  $item['authorId'],
            'coAuthorId'        =>  $item['coAuthorId'],
            'secondCoAuthorId'  =>  $item['secondCoAuthorId'],

            'is_audio'  =>  (bool)$item['is_audio'],
            'is_adult'  =>  (bool)$item['is_adult'],
            'is_exclusive'  =>  (bool)$item['is_exclusive'],
            'is_promofragment'  =>  (bool)$item['is_promofragment'],

            'genres'        =>  $item['genres']
        ];

        return $dataset;
    }

}