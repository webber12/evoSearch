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
 * @internal    @properties &offset=Первая строка переиндексации;text;0 &rowsperonce=Строк за сеанс индексировать;text;1 &reindex=Переиндексировать все;text;0 &excludeTmpls=Исключить шаблоны;text; &excludeIDs=Исключить ID ресурсов;text; &TvNames=Имена TV для поиска;textarea; &unpublished=Индексировать неопубликованные;text;0 &deleted=Индексировать удаленные;text;0 &dicts=Использовать словари;text;rus,eng
 * @internal    @installset base, sample
 * @internal    @modx_category Search
 * @internal    @disabled 1
 */
 
/**
 * до первого запуска сниппета на фронтэнде сайта необходимо запустить индексацию (сохранить любой ресурс в админке)
 *
 * индексация запускается сохранением любого ресурса (вызовом события onDocFormSave)
 *
 * при первом запуске индексации или необходимости переиндексации необходимо выставить параметр "Переиндексировать все" = 1, начальные строки и количество строк за сеанс устанавливаются в зависимости от 
 * возможностей вашего хостинга (например 0 и 10 000 соответственно - проиндексирует строки с 0 в количестве 10 000 штук в БД
 * необходимо открыть и пересохранить любой документ для создания события onDocFormSave
 *
 * для последующей работы установите "Переиндексировать все" = 0, "Строк за сеанс индексировать" = 1 
 * при этом происходит переиндксация только того документа, который сохраняется
 *
 * индексируются pagetitle,longtitle,description,introtext,content и указанные явно в плагине ТВ (по именам через запятую)
 *
*/

return require MODX_BASE_PATH . "assets/plugins/evoSearch/evoSearch.plugin.php";
