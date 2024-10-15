## Получить информацию о книге (точнее, о главах)

`curl -H "Authorization: Bearer guest" -X GET https://api.author.today/v1/work/242498/content`

```json
[
  {
    "id": 2183512,
    "workId": 242498,
    "title": "Глава 1",
    "isDraft": false,
    "sortOrder": 0,
    "publishTime": "2023-01-01T00:08:35.887Z",
    "lastModificationTime": "2023-01-01T00:08:35.887Z",
    "textLength": 14502,
    "isAvailable": true
  }
]
```
Тут отдается publishTime, которая не отдается в следующем запросе:

## Получить мета-информацию по произведению:

https://api.author.today/help/api/get-v1-work-id-meta-info

`curl -H "Authorization: Bearer guest" -X GET https://api.author.today/v1/work/242498/meta-info`

```json
{
  "id": 242498,
  "title": "Ритуал купели",
  "coverUrl": "https://cm.author.today/content/2023/01/01/3d96b0ab59704e6e999ccf823af0375c.jpg?width=265&height=400&rmode=max",
  "lastModificationTime": "2023-01-01T00:08:37.747Z",
  "lastUpdateTime": "2023-01-01T00:18:52.683Z",
  "finishTime": null,
  "isFinished": false,
  "textLength": 14502,
  "textLengthLastRead": 0,
  "price": null,
  "discount": null,
  "workForm": "Story",
  "status": "Free",
  "authorId": 297853,
  "authorFIO": "Karel Wintersky",
  "authorUserName": "karelwintersky",
  "originalAuthor": null,
  "translator": null,
  "reciter": null,
  "coAuthorId": null,
  "coAuthorFIO": null,
  "coAuthorUserName": null,
  "coAuthorConfirmed": false,
  "secondCoAuthorId": null,
  "secondCoAuthorFIO": null,
  "secondCoAuthorUserName": null,
  "secondCoAuthorConfirmed": false,
  "isPurchased": false,
  "userLikeId": null,
  "lastReadTime": null,
  "lastChapterId": null,
  "lastChapterProgress": 0.0,
  "likeCount": 5,
  "commentCount": 4,
  "rewardCount": 0,
  "rewardsEnabled": false,
  "inLibraryState": "None",
  "addedToLibraryTime": "0001-01-01T00:00:00Z",
  "privacyDisplay": "All",
  "state": "Default",
  "isDraft": false,
  "enableRedLine": true,
  "enableTTS": true,
  "adultOnly": false,
  "seriesId": null,
  "seriesOrder": 0,
  "seriesTitle": null,
  "afterword": null,
  "seriesNextWorkId": null,
  "genreId": 10,
  "firstSubGenreId": null,
  "secondSubGenreId": null,
  "format": "EBook"
}
```

## Получить полную информацию о книге (97929)

`curl -H "Authorization: Bearer guest" -X GET https://api.author.today/v1/work/97929/details`

```json5
{
  "chapters": [
    {
      "id": 775095,
      "workId": 97929,
      "title": "Путешествие с Ар'ри",
      "isDraft": false,
      "sortOrder": 0,
      "publishTime": "2020-11-03T22:44:37.7Z",
      "lastModificationTime": "2021-09-02T05:49:04.32Z",
      "textLength": 13517,
      "isAvailable": true
    },
    {
      "id": 1189890,
      "workId": 97929,
      "title": "Виктор",
      "isDraft": false,
      "sortOrder": 1,
      "publishTime": "2021-09-03T13:50:45.333Z",
      "lastModificationTime": "2021-09-03T13:50:45.333Z",
      "textLength": 5590,
      "isAvailable": true
    },
    {
      "id": 1188157,
      "workId": 97929,
      "title": "Ошейник",
      "isDraft": false,
      "sortOrder": 2,
      "publishTime": "2021-09-02T06:06:24.49Z",
      "lastModificationTime": "2021-09-02T06:47:45.047Z",
      "textLength": 6177,
      "isAvailable": true
    }
  ],
  "allowDownloads": false,
  "downloadErrorCode": "AuthorizationRequired",
  "downloadErrorMessage": "Доступ ограничен. Требуется авторизация.",
  "privacyDownloads": "Disable",
  "annotation": "Это временный сборник отрывков для будущего (?) текста про Риана Сазерленда с планеты Новая Франция. Черновики будут публиковаться по мере сил, лени (её отсутствия) и под настроение. <br><br>Скорее всего, уважаемый читатель, ты не найдешь в этом тексте ничего интересного для себя. Проходи, сталкер, не задерживайся :-)",
  "authorNotes": "Важно: 18+, в некоторых главах нецензурные выражения.<br><br>Смотрите на свой страх и риск. Я предупредил.",
  "atRecommendation": false,
  "seriesWorkIds": [      // ID остальных книг в цикле 
    97929,
    167163
  ],
  "seriesWorkNumber": 1,  // Номер книги в цикле
  "reviewCount": 0,       // Количество рецензий
  "tags": [               // Тэги
    "18",
    "авторский мир",
    "источник the spring",
    "любовь",
    "любовь смерть и яблоки",
    "магия и приключения",
    "романтика",
    "становление героя",
    "фантастика"
  ],
  "orderId": null,
  "orderStatus": "None",
  "orderStatusMessage": null,
  "contests": [], // Список конкурсов, в которых участвует книга (RT: передавать количество конкурсов)
  "galleryImages": [
    {
      "id": "3ba3b429-f477-4b3c-a36a-4912545aa657",
      "caption": "Ар'ри, страж Источника",
      "url": "https://cm.author.today/content/2020/11/03/w/3ba3b429f4774b3ca36a4912545aa657.jpg",
      "height": 700,
      "width": 513
    }
  ],
  "booktrailerVideoUrl": null,
  "isExclusive": false,  // Флаг "эксклюзив"
  "freeChapterCount": 0, // Количество бесплатных глав
  "promoFragment": false, // Флаг "Промо-фрагмент"
  "recommendations": [],
  "linkedWork": null,
  "id": 97929,
  "title": "В каждом миге видеть вечность",
  "coverUrl": "https://cm.author.today/content/2020/11/03/48172f3d33db47bb80f477b9547e92d0.jpg?width=265&height=400&rmode=max",
  "lastModificationTime": "2021-09-02T06:06:24.49Z",
  "lastUpdateTime": "2021-09-03T13:51:55.493Z",
  "finishTime": null,
  "isFinished": false,
  "textLength": 25284,
  "textLengthLastRead": 0,
  "price": null,
  "discount": null,
  "workForm": "StoryBook", // Форма произведения: роман, рассказ и т.п https://api.author.today/help/resourcemodel?modelName=WorkFormEnum
  "status": "Free", // https://api.author.today/help/resourcemodel?modelName=WorkStatus 
  "authorId": 297853,
  "authorFIO": "Karel Wintersky",
  "authorUserName": "karelwintersky",
  "originalAuthor": null,
  "translator": null,
  "reciter": null,
  "coAuthorId": null,
  "coAuthorFIO": null,
  "coAuthorUserName": null,
  "coAuthorConfirmed": false,
  "secondCoAuthorId": null,
  "secondCoAuthorFIO": null,
  "secondCoAuthorUserName": null,
  "secondCoAuthorConfirmed": false,
  "isPurchased": false,
  "userLikeId": null,
  "lastReadTime": null,
  "lastChapterId": null,
  "lastChapterProgress": 0.0,
  "likeCount": 12, // Количество лайков у книги
  "commentCount": 15, // Количество комментариев у книги
  "rewardCount": 0, // Количество наград у книги
  "rewardsEnabled": false,
  "inLibraryState": "None",
  "addedToLibraryTime": "0001-01-01T00:00:00Z",
  "privacyDisplay": "All",
  "state": "Default",
  "isDraft": false,
  "enableRedLine": true,
  "enableTTS": true,
  "adultOnly": true, // Флаг "18+"
  "seriesId": 5974,
  "seriesOrder": 0,
  "seriesTitle": "Федерация: лики хаоса",
  "afterword": null,
  "seriesNextWorkId": null,
  "genreId": 63, // Жанр основной (справочник?)
  "firstSubGenreId": 53, // Второй жанр
  "secondSubGenreId": 62, // Третий жанр
  "format": "EBook"
}              
```