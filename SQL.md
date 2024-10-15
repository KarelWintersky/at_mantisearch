```sql
CREATE TABLE index_authors (
	id int auto_increment NOT NULL,
	login varchar(100) DEFAULT '' COMMENT 'author login',
	latest_fetch datetime NULL COMMENT 'latest fetch from sitemap timestamp',
	latest_parse datetime NULL COMMENT 'latest data update timestamp' ,
	need_update tinyint NOT NULL DEFAULT 0 COMMENT 'need update author (parse < fetch)',
	PRIMARY KEY (id),
	KEY latest_fetch (latest_fetch),
	KEY latest_parse (latest_parse)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

CREATE TABLE index_posts (
	id int auto_increment NOT NULL,
	login varchar(100) DEFAULT '' COMMENT 'author login',
	latest_fetch datetime NULL COMMENT 'latest fetch from sitemap timestamp',
	latest_parse datetime NULL COMMENT 'latest data update timestamp', 
	need_update tinyint NOT NULL DEFAULT 0 COMMENT 'need update post (parse < fetch)',
	PRIMARY KEY (id),
	KEY latest_fetch (latest_fetch),
	KEY latest_parse (latest_parse)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

CREATE TABLE index_works (
	id int auto_increment NOT NULL,
	work_id int not null DEFAULT 0 comment 'work id',
	latest_fetch datetime NULL COMMENT 'latest fetch from sitemap timestamp',
	latest_parse datetime NULL COMMENT 'latest data update timestamp', 
	need_update tinyint NOT NULL DEFAULT 0 COMMENT 'need update work (parse < fetch)',
	PRIMARY KEY (id),
	KEY latest_fetch (latest_fetch),
	KEY latest_parse (latest_parse)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;


```


```sql
CREATE TABLE works (
	id int auto_increment NOT NULL COMMENT 'id книги',
	work_id int not null DEFAULT 0 comment 'work id',

	+lastmod datetime NULL COMMENT 'NOW()',
	+is_need_update tinyint DEFAULT 0 COMMENT 'флаг требуется обновление через АПИ',

	title varchar(250) DEFAULT '' COMMENT 'название, обрезанное до 250 символов, лимит АТ 150',
	annotation text NULL COMMENT 'аннотация HTML',
	author_notes text NULL COMMENT 'примечания автора HTML',
	cover_url varchar(250) DEFAULT '' COMMENT 'ссылка на обложку',

	series_works_ids varchar(250) DEFAULT '' COMMENT 'остальные книги цикла, строка IDs через запятую',
	series_works_this int DEFAULT 1 COMMENT 'номер книги в цикле',

	series_id int NULL COMMENT 'id цикла',
	series_order int DEFAULT 0 COMMENT 'порядок в цикле',
	series_title varchar(150) DEFAULT '' COMMENT 'название цикла',

	is_exclusive tinyint DEFAULT 0 COMMENT 'флаг эксклюзив',
	is_promofragment tinyint DEFAULT 0 COMMENT 'флаг промо-фрагмент',
	is_finished tinyint DEFAULT 0 COMMENT 'флаг книга завершена',
	is_draft tinyint DEFAULT 0 COMMENT 'книга находится в черновиках',
	is_adult tinyint DEFAULT 0 COMMENT 'флаг 18+',
	is_adult_pwp tinyint DEFAULT 0 COMMENT 'флаг 18+, порно без сюжета',

	time_last_update datetime NULL COMMENT 'последняя модификация любой составляющей книги',
	time_last_modification datetime NULL COMMENT 'дата последнего изменения текста на +15к знаков',
	time_finished datetime NULL COMMENT 'дата завершения книги, если флаг завершения 1',

	text_length int NULL COMMENT 'длина книги',

	price decimal(8,4) NULL COMMENT 'цена книги в рублях',

	work_form enum('Any', 'Story', 'Novel', 'StoryBook', 'Poetry', 'Translation', 'Tale') NOT NULL DEFAULT 'Any' COMMENT 'форма произведения, ENUM',

	work_status enum('Free', 'Subscription', 'Sales', 'Suspended') NOT NULL DEFAULT 'Free' COMMENT 'статус книги',

    authorId int NULL COMMENT 'ID автора',
    authorFIO varchar(100) NULL COMMENT 'ФИО автора',
    authorUserName varchar(100) NULL COMMENT 'логин автора',

   	coAuthorId int NULL COMMENT 'ID соавтора №1',
	coAuthorFIO varchar(100) NULL COMMENT 'ФИО соавтора №1',
	coAuthorUserName varchar(100) NULL COMMENT 'логин соавтора №1',

	secondCoAuthorId INT NULL COMMENT 'ID соавтора №2',
	secondCoAuthorFIO varchar(100) NULL COMMENT 'ФИО соавтора №2',
	secondCoAuthorUserName varchar(100) NULL COMMENT 'логин соавтора №2',

	count_like int DEFAULT 0 COMMENT 'количество лайков у книги',
	count_comments int DEFAULT 0 COMMENT 'комментов у книги',
	count_rewards int DEFAULT 0 COMMENT 'наград у книги',
	count_chapters int DEFAULT 0 COMMENT 'кол-во глав (элементов массива chapters)',
	count_chapters_free int DEFAULT 0 COMMENT 'количество бесплатных глав',
	count_review int DEFAULT 0 COMMENT 'количество рецензий',

	genre_main int NULL COMMENT 'жанр основной',
	genre_2nd int NULL COMMENT 'жанр второй',
	genre_3rd int NULL COMMENT 'жанр третий',
	genres varchar(250) DEFAULT '' COMMENT 'id жанров, jsonized array, для MVA',

	tags varchar(250) DEFAULT '' COMMENT 'ПОКА ПУСТО список айдишников тегов (MVA)',
	tags_text text COMMENT 'строка из тегов через запятую в сыром виде',

	entity_state ENUM('DeletedAndHidden', 'Default', 'DeletedByUser', 'DeletedByModerator', 'DeletedByAuthor', 'HiddenByDeactivatingAccount') DEFAULT 'Default' COMMENT 'состояние книги',
	entity_format ENUM('Any', 'EBook', 'Audiobook') DEFAULT 'Any' COMMENT 'WorkFormatEnum',
	entity_privacy ENUM('All', 'OnlyFriends') DEFAULT 'All' COMMENT 'Приватность',

	CONSTRAINT works_PK PRIMARY KEY (id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

ALTER TABLE works ADD need_update tinyint DEFAULT 1 NULL COMMENT 'нужно обновление данных';
```

```sql
CREATE TABLE posts (
	id int auto_increment NOT NULL,
	lastmod datetime NULL,
	lastmod_ts int NULL,
	is_public tinyint NULL COMMENT 'публичный ли пост',
	`type` varchar(100) NULL COMMENT 'тип записи (личное, самопиар итп)',
	published datetime NULL COMMENT 'опубликовано',
	last_update datetime NULL COMMENT 'последнее редактирование',
	rating int NULL,
	views int NULL,
	comments_count int NULL,
	tags text NULL COMMENT 'JSON, список тегов для MVA',
	content longtext NULL COMMENT 'текст html',
	is_review tinyint DEFAULT 0 NULL COMMENT 'является ли рецензией',
	review_work_id INT DEFAULT 0 NULL COMMENT 'ID произведения, на которое этот пост является рецензией',
	CONSTRAINT posts_PK PRIMARY KEY (id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE posts ADD need_update tinyint DEFAULT 1 NULL COMMENT 'нужно обновление данных';
```

```sql
CREATE TABLE work_tags (
	id int auto_increment NOT NULL,
	hash BINARY(32) NULL COMMENT 'md5 хэш от тайтла',
	urn varchar(250) NULL COMMENT 'url-encoded текст тега',
	title varchar(250) NULL COMMENT 'текст тега в нормальном UTF-8 виде',
	CONSTRAINT work_tags_PK PRIMARY KEY (id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
CREATE UNIQUE INDEX work_tags_hash_IDX USING BTREE ON atfinder.work_tags (hash);
```


-----

```sql
CREATE TABLE authors (
	id int auto_increment NOT NULL,
	login varchar(100) DEFAULT '' NULL COMMENT 'author login',
	lastmod datetime NULL COMMENT 'дата последней модификации',
	lastmod_ts int NULL COMMENT 'таймштамп последней модификации',
	fio varchar(100) NULL COMMENT 'имя',
	reputation int DEFAULT 0 NULL COMMENT 'репутация автора',
	rating int DEFAULT 0 NULL COMMENT 'рейтинг автора',
	avatar varchar(250) DEFAULT '' NULL COMMENT 'ссылка на аватар',
	register_date datetime NULL COMMENT 'дата регистрации',
	friends int DEFAULT 0 NULL COMMENT 'друзья',
	followers int DEFAULT 0 NULL COMMENT 'подписчики',
	`following` int DEFAULT 0 NULL COMMENT 'подписки',
	count_blogs int DEFAULT 0 NULL COMMENT 'публичных записей в блоге',
	count_works int DEFAULT 0 NULL COMMENT 'публичных произведений всех типов',
	about longtext NULL COMMENT 'о себе, текст, htmlstripped',
	slogan varchar(250) DEFAULT '' NULL COMMENT 'девиз автора',
	banned tinyint DEFAULT 0 NULL COMMENT 'забанен ли',
	banned_until datetime NULL COMMENT 'дата разбана',
	birthday DATE NULL COMMENT 'дата рождения или 0 если не указана полностью',
	CONSTRAINT posts_PK PRIMARY KEY (id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4;

ALTER TABLE authors ADD need_update tinyint DEFAULT 1 NULL COMMENT 'нужно обновление данных';
```

