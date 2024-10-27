# Реально нужные поля
 
- id работы
- ссылка на обложку
- название
- автор(ы) - ФИО, логин, id
  - 1 автор
  - 2 автор
  - 3 автор
- аннотация
- теги
- жанры 
  - первый
  - второй
  - третий
- тип книги (Book / Audio)
- длина в символах
- длина в часах/минутах
- признак: is_finished
- дата последнего обновления
- дата завершения
- цена (0 = free)
- id цикла
- название цикла
- просмотры
- лайки
- комменты
- рецензии

```sql
CREATE TABLE `works` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `work_id` int(11) NOT NULL DEFAULT 0 COMMENT 'work id',
    
    `latest_fetch` datetime DEFAULT NULL COMMENT 'latest fetch from sitemap timestamp',
    `latest_parse` datetime DEFAULT NULL COMMENT 'latest data download and parse timestamp',
    
    `need_update` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'need update work (parse < fetch)',
    `need_delete` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'need delete work (not found in new index)',
    
    `work_form` enum('Any','Story','Novel','StoryBook','Poetry','Translation','Tale') NOT NULL DEFAULT 'Any' COMMENT 'форма произведения, ENUM',
    `work_status` enum('Free','Subscription','Sales','Suspended') NOT NULL DEFAULT 'Free' COMMENT 'статус книги',
    `work_state` enum('DeletedAndHidden','Default','DeletedByUser','DeletedByModerator','DeletedByAuthor','HiddenByDeactivatingAccount') DEFAULT 'Default' COMMENT 'состояние книги',
    `work_format` enum('Any','EBook','Audiobook') DEFAULT 'Any' COMMENT 'WorkFormatEnum',
    `work_privacy` enum('All','OnlyFriends') DEFAULT 'All' COMMENT 'Приватность',
    
    `is_broken` tinyint(4) DEFAULT 0 COMMENT 'контент сломан (строки невозможно нормализовать или вставить в БД)',
    `is_audio` tinyint(4) DEFAULT 0 COMMENT 'это аудиокнига',
    `is_exclusive` tinyint(4) DEFAULT 0 COMMENT 'флаг эксклюзив',
    `is_promofragment` tinyint(4) DEFAULT 0 COMMENT 'флаг промо-фрагмент',
    `is_finished` tinyint(4) DEFAULT 0 COMMENT 'флаг книга завершена',
    `is_draft` tinyint(4) DEFAULT 0 COMMENT 'книга находится в черновиках',
    `is_adult` tinyint(4) DEFAULT 0 COMMENT 'флаг 18+',
    `is_adult_pwp` tinyint(4) DEFAULT 0 COMMENT 'флаг 18+, порно без сюжета',
    
    `count_like` int(11) DEFAULT 0 COMMENT 'количество лайков у книги',
    `count_comments` int(11) DEFAULT 0 COMMENT 'комментов у книги',
    `count_rewards` int(11) DEFAULT 0 COMMENT 'наград у книги',
    `count_chapters` int(11) DEFAULT 0 COMMENT 'кол-во глав (элементов массива chapters)',
    `count_chapters_free` int(11) DEFAULT 0 COMMENT 'количество бесплатных глав',
    `count_review` int(11) DEFAULT 0 COMMENT 'количество рецензий',
    
    `time_last_update` datetime DEFAULT NULL COMMENT 'последняя модификация любой составляющей книги',
    `time_last_modification` datetime DEFAULT NULL COMMENT 'дата последнего изменения текста на +15к знаков',
    `time_finished` datetime DEFAULT NULL COMMENT 'дата завершения книги, если флаг завершения 1',
    
    `text_length` int(11) DEFAULT 0 COMMENT 'длина книги в знаках',
    `audio_length` int(11) DEFAULT 0 COMMENT 'длительность аудикниги в минутах',
    
    `price` decimal(8,4) DEFAULT NULL COMMENT 'цена книги в рублях',
    
    `title` varchar(250) DEFAULT '' COMMENT 'название, обрезанное до 250 символов, лимит АТ 150',
    `annotation` text DEFAULT NULL COMMENT 'аннотация HTML',
    `author_notes` text DEFAULT NULL COMMENT 'примечания автора HTML',
    `cover_url` varchar(250) DEFAULT '' COMMENT 'ссылка на обложку',
    `series_id` int(11) DEFAULT NULL COMMENT 'id цикла',
    `series_order` int(11) DEFAULT 0 COMMENT 'порядок в цикле',
    `series_title` varchar(150) DEFAULT '' COMMENT 'название цикла',
    
    `tags` varchar(250) DEFAULT '' COMMENT '[UNUSED] jsonized ids array for MVA',
    `tags_text` text DEFAULT NULL COMMENT 'строка из тегов через запятую в сыром виде',
    
    `authorId` int(11) DEFAULT NULL COMMENT 'ID автора',
    `authorFIO` varchar(100) DEFAULT NULL COMMENT 'ФИО автора',
    `authorUserName` varchar(100) DEFAULT NULL COMMENT 'логин автора',
    
    `coAuthorId` int(11) DEFAULT NULL COMMENT 'ID соавтора №1',
    `coAuthorFIO` varchar(100) DEFAULT NULL COMMENT 'ФИО соавтора №1',
    `coAuthorUserName` varchar(100) DEFAULT NULL COMMENT 'логин соавтора №1',
    
    `secondCoAuthorId` int(11) DEFAULT NULL COMMENT 'ID соавтора №2',
    `secondCoAuthorFIO` varchar(100) DEFAULT NULL COMMENT 'ФИО соавтора №2',
    `secondCoAuthorUserName` varchar(100) DEFAULT NULL COMMENT 'логин соавтора №2',
    
    `genre_main` int(11) DEFAULT NULL COMMENT 'жанр основной',
    `genre_2nd` int(11) DEFAULT NULL COMMENT 'жанр второй',
    `genre_3rd` int(11) DEFAULT NULL COMMENT 'жанр третий',
    `genres` varchar(250) DEFAULT '' COMMENT 'id жанров, jsonized ids array for MVA',
    
    PRIMARY KEY (`id`),
    KEY `work_id` (`work_id`),
    KEY `latest_fetch` (`latest_fetch`),
    KEY `latest_parse` (`latest_parse`),
    KEY `is_audio` (`is_audio`),
    KEY `need_update` (`need_update`),
    KEY `need_delete` (`need_delete`),
    KEY `work_format` (`work_format`)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```