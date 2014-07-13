//<?php
/**
 * evoSearch
 * 
 * Вывод результатов поиска
 *
 * @author	    webber (web-ber12@yandex.ru)
 * @category 	snippet
 * @version 	0.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@modx_category Search
 * @internal    @installset base, sample
 */
 
//поиск по сайту с учетом словоформ (словаря phpMorphy)
//работает совместно с плагином evoSearch (плагин индексирует, сниппет выводит результаты)
//для работы необходим установленный сниппет DocLister
//пример вызова - для вывода результатов [!evoSearch? &tpl=`evoSearch`!], 
//ПАРАМЕТРЫ
// + &noResult = `Ничего не найдено` - строка, которая выводится при отсутствии результата поиска )необязательно)
// + &addSearch = `0` - для опционального отключения дополнительного поиска при пустом fulltext-search (по умолчанию - 1)
// + &extract=`0` - отключить экстрактор формирует нужную часть текста с подсветкой из результатов поиска (плейсхолдер [+extract+] в чанке вывода результатов DocLister) - по умолчанию 1 (не извлекать)
// + &maxlength=`300` - максимальная длина извлекаемой части текста в резуьлтатах поиска (по умолчанию 350)
// + &show_stat = `0` - отключаем показ статистики "найдено....показано...с...по...". По умолчанию - 1 - показ включен
// + &statTpl - шаблон показа статистики (по умолчанию - <div class="evoSearch_info">По запросу <b>[+stat_request+]</b> найдено всего <b>[+stat_total+]</b>. Показано <b>[+stat_display+]</b>, c [+stat_from+] по [+stat_to+]</div> ), где
//              [+stat_request+] - запрос из строки $_GET['search']
//              [+stat_total+] - найдено всего
//              [+stat_display+] - показано на текущей странице с [+stat_from+] по [+stat_to+] 
//остальные параметры - дублируют параметры вызова DocLister
//обрабатывает $_GET['search'] в качестве входной строки для поиска

require_once MODX_BASE_PATH . "assets/snippets/evoSearch/evoSearch.snippet.php";

