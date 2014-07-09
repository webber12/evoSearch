/**
 * evoSearch
 *
 * Плагин для индексации и поиска
 *
 * @author      webber (web-ber12@yandex.ru)
 * @category    plugin
 * @version     0.1
 * @license     http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal    @events OnDocFormSave
 * @internal    @properties &offset=Первая строка переиндексации;text;0 &rowsperonce=Строк за сеанс индексировать;text;1 &reindex=Переиндексировать все;text;0 &excludeTmpls=Исключить шаблоны;text; &excludeIDs=Исключить ID ресурсов;text; &TvIDs=ID TV для поиска;text;  &unpublished=Индексировать неопубликованные;text;0 &deleted=Индексировать удаленные;text;0 &dicts=Использовать словари;text;rus,eng
 * @internal    @installset base, sample
 * @internal    @modx_category Search
 */
 
/**
 * 1. создаем два поля с типом mediumtext в таблице site_content - content_with_tv и content_with_tv_index для хранения оригинала текста  (из поля контента) плюс нужных значений тв и их словоформ 
 * 2. создаем индекс 
 * ALTER TABLE `modx_site_content` ADD FULLTEXT `content_index` (`content_with_tv`, `content_with_tv_index`);
 * 3. создаем еще один индекс
 * ALTER TABLE `modx_site_content` ADD FULLTEXT(`pagetitle`);
 * 
 *
 * при первом запуске или необходимости переиндексации необходимо выставить параметр "Переиндексировать все" = 1, начальные строки и количество строк за сеанс устанавливаются в зависимости от 
 * возможностей вашего хостинга (например 0 и 10 000 соответственно - проиндексирует строки с 0 в количестве 10 000 штук в БД
 * необходимо открыть и пересохранить любой документ для создания события onDocFormSave
 *
 * для последующей работы установите "Переиндексировать все" = 0, "Строк за сеанс индексировать" = 1 
 * при этом происходит переиндксация только того документа, который сохраняется
 *
 * в настоящий момент индексации по TV не производится, индексируются только pagetitle,longtitle,description,introtext.content
 *
*/

require_once MODX_BASE_PATH."assets/plugins/evoSearch/evoSearch.plugin.php";
