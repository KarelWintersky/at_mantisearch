https://github.com/KarelWintersky/at_mantisearch

# +

https://manual.manticoresearch.com/Server_settings/Searchd#not_terms_only_allowed

# Примеры запросов

```
select work_id, title, authorFIO from rt_at_works where match('@title "Записки оператора"');

select work_id, title, authorFIO from rt_at_works where match('@title "Записки*"') limit 20;

select work_id, title, authorFIO from rt_at_works where match('@title "Записки*" @authorFIO "Karel*"') limit 20;

select work_id, title, authorFIO from rt_at_works where match('@title кузнец')  order by work_id asc limit 20;

select work_id, title, authorFIO from rt_at_works where match('@title кузнец -рун')  order by work_id asc limit 20;

select work_id, title, authorFIO, genres from rt_at_works where match('@authorfio ренсинк') and genres in (58) and genres not in (0) order by work_id asc limit 20;

select work_id, title, authorFIO, genres from rt_at_works where match('@authorfio ренсинк') and genres in (58) and genres not in (2) order by work_id asc limit 20;

select work_id, title, authorFIO, genres from rt_at_works where match('@authorfio иевлев') order by work_id asc limit 40;

select work_id, is_audio, title, authorfio, genres from rt_at_works where match('@reciter Олег Кейнз') order by work_id asc limit 4000;

-- строгая форма слова
select work_id, title, authorFIO, tags_text from rt_at_works where match('@tags_text ="юри"');
```

https://manual.manticoresearch.com/Searching/Full_text_matching/Operators#Full-text-operators
