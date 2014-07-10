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
//пример вызова - для вывода результатов [!evoSearch? &tpl=`evoSearch`!], где все параметры - дублируют параметры вызова DocLister, 
// + параметр noResult - строка, которая выводится при отсутствии результата поиска
//обрабатывает $_GET['search'] в качестве входной строки для поиска

require_once MODX_BASE_PATH . "assets/snippets/evoSearch/evoSearch.snippet.php";

