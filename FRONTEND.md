Текущий вид результата поиска:
```html
<div class="book-row">
    <div class="book-cover-wrapper">
        <div class="book-cover mb-lg">
            <a data-pjax="" href="/work/298240" class="book-cover-content ">
                                                    <div class="cover-image fade-box ebook-cover-image">
                        <img src="https://cm.author.today/content/2023/09/29/91f722cb414b4639bac1b71f987802ba.jpg?width=153&amp;height=200&amp;rmode=max" alt="Обложка произведения <em class='searched-item'>Оператор</em>">
                    </div>
            </a>
        </div>

        
    </div>
    <div class="book-row-content">
        <div class="book-title">
            <a data-pjax="" href="/work/298240">
                <em class="searched-item">Оператор</em>
            </a>
            <div class="pull-right work-list-actions">
                <library-button params="{workId: 298240, format: 'EBook', state: 'None', onlyIcon: true, leftMenu: true}"><noindex>
    <div data-bind="style: {display : inlineBtn ? 'inline-block' : 'block'}" class="dropdown" style="display: block;">
        <button data-toggle="dropdown" data-bind="btn: processing, css: {'btn-with-icon': inlineBtn, 'btn-block': !inlineBtn,
            'btn-in-library': (currentState() !== 'None' &amp;&amp; currentState() !== 'Disliked'), 'btn-library-dislike': (currentState() === 'Disliked'), 'text-truncate': !onlyIcon}" class="btn btn-default btn-block">
            <!-- ko with: stateForDropdown -->
            <i data-bind="css: icon" class="icon-plus"></i> <span data-bind="text: $parent.onlyIcon? '' : title"></span>
            <!-- /ko -->
        </button>
        <ul data-bind="foreach: statesToUpdate(), css: {'dropdown-menu-right': leftMenu}" class="dropdown-menu dropdown-menu-links dropdown-menu-right" aria-labelledby="Изменить статус книги в библиотеке">
            <li>
                <a data-bind="click: $parent.updateLibrary" class="pl"><i data-bind="css: icon" class="mr-sm icon-2-library-reading"></i> <span data-bind="text: title">Читаю</span></a>
            </li>
        
            <li>
                <a data-bind="click: $parent.updateLibrary" class="pl"><i data-bind="css: icon" class="mr-sm icon-2-clock"></i> <span data-bind="text: title">Отложено на потом</span></a>
            </li>
        
            <li>
                <a data-bind="click: $parent.updateLibrary" class="pl"><i data-bind="css: icon" class="mr-sm icon-2-library-finished"></i> <span data-bind="text: title">Прочитано</span></a>
            </li>
        
            <li>
                <a data-bind="click: $parent.updateLibrary" class="pl"><i data-bind="css: icon" class="mr-sm icon-eye-slash"></i> <span data-bind="text: title">Не интересно</span></a>
            </li>
        </ul>
    </div>
</noindex></library-button>
            </div>
        </div>
            <div class="book-author"><a data-pjax="" href="/u/nil/works">Виктор Волков</a></div>
        <div data-pjax="" class="book-genres"><a href="/work/genre/all/any/novel">Роман</a> / <a href="/work/genre/sci-fi">Фантастика</a>, <a href="/work/genre/sf-action">Боевая фантастика</a>, <a href="/work/genre/cyberpunk">Киберпанк</a></div>
        <div class="row book-details">
            <div class="col-xs-6">
                <div>
                        <i class="icon-book2 text-dark"></i>
<span class="hint-top" data-hint="Размер, кол-во знаков с пробелами">385&nbsp;615 зн.</span>, 9,64 <abbr class="hint-top" data-hint="Авторский лист - 40 000 печатных знаков">а.л.</abbr>                </div>
                <div>
                        <span class="text-success">Свободный доступ</span>
                </div>
            </div>
            <div class="col-xs-6">
                <div>
                        <span class="text-success"><i class="icon-check-bold book-status-icon text-success text-bold"></i>
                            весь текст
                        </span>
                        <span class="stats-sep"></span>
                        <span><span class="hint-top" data-format="calendar" data-hint="Завершено 19 октября 2023 в 12:40:21" data-time="2023-10-19T09:40:21.8600000Z">19 октября 2023</span></span>
                </div>
                
            </div>
        </div>

        <div class="book-stats">
            <span data-hint="Просмотры · 7&nbsp;034" class="hint-top"><i class="icon-eye"></i> 7&nbsp;034</span>
            <span data-hint="Понравилось · 85" class="hint-top">
                <button class="btn-like disabled" disabled="disabled" type="button">
                    <div class="heart-container"><div class="heart"></div></div><span class="like-count">85</span> 
                </button>
            </span>
            <span data-hint="Комментарии · 70" class="hint-top">
                <i class="icon-comments"></i>
                <a href="/work/298240#comments">70</a>
            </span>
            <span data-hint="Рецензии · 3" class="hint-top">
                <i class="icon-feather"></i>
                <a href="/work/298240/reviews">3</a>
            </span>
        </div>

        <div data-bind="read-more: {}" class="annotation" style="max-height: none;">
Центр. Большой город, в котором шумит инфополе, где в одних районах кипит жизнь, в других царит запустение или же вообще нет людей, и лишь работают автоматические фабрики. На междугороднем беспилотном такси прибывает новый сотрудник полиции Восточного Округа. Участвовать в расследованиях, и повседневной жизни.        </div>
    </div>
</div>
```

https://author.today/audiobook/257399

https://author.today/work/229856
https://author.today/work/301362 - несколько авторов

То есть:

- обложка (ссылка на страницу книги)
- название (ссылка на страницу книги)
- автор(ы) (ссылка на работы авторов)
- список жанров
- 
  - иконка книги, длина в символах, длина в алках
  - иконка аудио, длина в часах 
  - (весь текст | в процессе) - иконка и подпись
  - (аудиокнига завершена)
  - дата (последнего обновления)
- 
  - свободный доступ | цена XXX
  - пусто | цикл: название/ссылка
- ...
  - просмотры (иконка и число)
  - лайки (иконка, число)
  - комменты (иконка, число)
  - рецензии (иконка, число)
- аннотация



