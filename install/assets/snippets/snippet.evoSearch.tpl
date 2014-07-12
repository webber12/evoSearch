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
// + &extract=`1` - формирует нужную часть текста с подсветкой из результатов поиска (плейсхолдер [+extract+] в чанке вывода результатов DocLister) - по умолчанию 0 (не извлекать)
// + &maxlength=`300` - максимальная длина извлекаемой части текста в резуьлтатах поиска (по умолчанию 350)
//остальные параметры - дублируют параметры вызова DocLister
//обрабатывает $_GET['search'] в качестве входной строки для поиска

require_once MODX_BASE_PATH . "assets/snippets/evoSearch/evoSearch.snippet.php";

