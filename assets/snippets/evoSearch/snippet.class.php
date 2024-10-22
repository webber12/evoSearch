<?php 

class evoSearchSnippet {

public $params = array();

//phpmorphy dir
public $phpmorphy_dir;

//dictionaries dir
public $dict_dir;

//min word length for search
public $min_length;

//using dictionaries array
public $dicts = array();

//поле содержащее информацию из поля content и других полей для поиска (pagetitle, TVs и т.п.)
public $ext_content_field;

//сформированный на основе $ext_content_field индекс из словоформ этого поля
public $ext_content_index_field;

//поисковая строка (оригинальная, пропущенная через escape $_GET['search']
public $txt_original = '';

//словоформы всех слов из поисковой строки, массив
public $txt_ext_array = array();

//словоформы всех слов из поисковой строки, строка. Слова разделены пробелами. Основной текст для организации полнотекстового поиска
public $txt_ext = array();

protected $modx;
public $search_field;
protected $id;
protected $content_table;
protected $search_table;
public $action;
public $stemmer;
protected $original;
public $search_words;
protected $uppercase_search_words;
protected $baseform_search_words;
public $bulk_words_stemmer;

public function __construct($modx, $params)
{
    $this->modx = $modx;
    $this->params = $params;
    $this->search_field = isset($params['search_field']) ? $params['search_field'] : 'search';
    $this->phpmorphy_dir = MODX_BASE_PATH . 'assets/lib/phpmorphy';
    $this->dict_dir = $this->phpmorphy_dir . '/dicts';
    $this->dicts = array('rus', 'eng');
    
}

public function init($min_length = 2, $ext_content_field = 'content_with_tv', $ext_content_index_field = 'content_with_tv_index')
{
    $this->id = isset($this->params['id']) ? $this->params['id'] : 0;
    $this->content_table = $this->modx->getFullTableName("site_content");
    $this->search_table = $this->modx->getFullTableName("evosearch_table");
    $this->ext_content_field = $ext_content_field;
    $this->ext_content_index_field = $ext_content_index_field;
    $this->action = isset($this->params['action']) ? $this->params['action'] : '';
    $this->setDefault(
        array(
            'display' => '20',
            'show_stat' => '1',
            'extract' => '1',
            'statTpl' => '<div class="evoSearch_info">По запросу <b>[+stat_request+]</b> найдено всего <b>[+stat_total+]</b>. Показано <b>[+stat_display+]</b>, c [+stat_from+] по [+stat_to+]</div>',
            'rel' => '0.01',
            'min_length' => $min_length,
            'debug' => '0'
            )
    );
    $this->min_length = $this->params['min_length'];
    $this->stemmer = $this->getStemmer();
    return $this;
}

public function setDefault($param, $default = '')
{
    if (is_array($param)) {
        foreach ($param as $p => $v) {
            $this->params[$p] = isset($this->params[$p]) ? $this->params[$p] : $v;
        }
    } else {
        $this->params[$param] = isset($this->params[$param]) ? $this->params[$param] : $default;
    }
    return $this;
}

public function makeWordsFromText($text)
{
    $words = array();
    $words = preg_replace('#\[.*\]#isU', '', $text);
    $words = str_replace(array('&ndash;', '&raquo;', '&laquo;', '&darr;', '&rarr;', '&mdash;'), array('', '', '', '', '', ''), $words);
    $words = preg_split('#\s|[,.:;!?"\'()]#', $text, -1, PREG_SPLIT_NO_EMPTY);
    return $words;
}

public function makeBulkWords($words, $upper = true)
{
    $bulk_words = array();
    foreach ($words as $v) {
        if (mb_strlen($v, "UTF-8") > $this->min_length) {
            $bulk_words[] = $upper ? mb_strtoupper($v, "UTF-8") : $v;
        }
    }
    return $bulk_words;
}

public function prepareWords()
{
    //string 
    //оригинальный запрос, очищенный от тегов
    $this->Set('txt_original', $this->sanitarTag($_GET[$this->search_field]), true);
    $this->original = $this->Get('txt_original');
    
    //array()
    //оригинальные слова из поиска длиннее min_length
    $this->search_words = $this->makeWordsFromText($this->Get('txt_original'));
    foreach ($this->search_words as $k => $word) {
        if (mb_strlen($word, "UTF-8") <= $this->min_length) {
            unset($this->search_words[$k]);
        }
    }
    
    //array()
    //те же слова в верхнем регистре
    $this->uppercase_search_words = $this->makeBulkWords($this->search_words);

    //нормализованные слова в базовой форме
    $this->baseform_search_words = array();
    $tmp = $this->Words2BaseForm(implode(' ', $this->uppercase_search_words));
    foreach ($tmp as $v) {
        if (is_array($v)) {
            foreach ($v as $v1) {
                if ($v1 && !empty($v1) && $v1 != '') {
                    $this->baseform_search_words[] = $v1;
                }
            }
        } else {
            if ($v && !empty($v) && $v != '') {
                $this->baseform_search_words[] = $v;
            }
        }
    }
}

//функция для prepare-сниппета DocLister (готовим данные для плейсхолдера [+extract+] в чанк вывода результатов DocLister
public function prepareExtractor($data, $modx, $DL, $extDL)
{
    if(!empty($data)) {
        $data = $this->makeHighlight($data);
    }
    return $data;
}
//делаем подсветку на основе стеммера
public function makeHighlight($data)
{
    if (is_array($this->bulk_words_stemmer) && !empty($this->bulk_words_stemmer)) {
        $input = implode('|', $this->bulk_words_stemmer);
        $input = str_replace(array('\\', '/'), array('', '\/'), $input);
        $pattern = '/(' . $input . ')([^\.\s\;\:"\'\(\)!?,]*)?/ius';
        $replacement = '<span class="evoSearch_highlight">$1$2</span>';
        if (isset($this->params['extract_with_tv']) && $this->params['extract_with_tv'] == '1') {
            $text = $this->getTextForHighlight($data[$this->ext_content_field]);
        } else{
            $text = $this->getTextForHighlight($data["content"] ?: $data["introtext"]);
        }
        $pagetitle = $this->modx->stripTags($data["pagetitle"] ?: '');
        $data["extract"] = preg_replace($pattern, $replacement, $text);
        $data["pagetitle"] = preg_replace($pattern, $replacement, $pagetitle);
    }
    return $data;
}

//вырезаем нужный кусок текста нужной длины (примерно)
protected function getTextForHighlight($text)
{
    $max_length = isset($this->params['maxlength']) && (int)$this->params['maxlength'] != 0 ? (int)$this->params['maxlength'] : 350;
    $limit = $max_length + 12;
    $text = $this->modx->stripTags($text ?: '');
    $pos = array();
    foreach ($this->bulk_words_stemmer as $word) {
        $pos[$word] = mb_strripos(mb_strtolower($text, 'UTF-8'), $word, 0, 'UTF-8');
    }
    foreach ($pos as $word => $position) {
        $length = mb_strlen($text, 'UTF-8');
        if ($position == 0 && $length > $limit) {
            $text = mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
        } else if ($position < $max_length && $length > $limit) {
            $text = ' ... ' . mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
        } else if ($position + $limit >= $length && $length > $limit) {
            $text = mb_substr($text, $position);
        } else if ($length > $limit){
            $text = ' ... ' . mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
        } else {

        }
    }
    return $text;
}

public function Words2BaseForm($text)
{
    require_once($this->phpmorphy_dir . '/src/common.php');

    // set some options
    $opts = array(
        // storage type, follow types supported
        // PHPMORPHY_STORAGE_FILE - use file operations(fread, fseek) for dictionary access, this is very slow...
        // PHPMORPHY_STORAGE_SHM - load dictionary in shared memory(using shmop php extension), this is preferred mode
        // PHPMORPHY_STORAGE_MEM - load dict to memory each time when phpMorphy intialized, this useful when shmop ext. not activated. Speed same as for PHPMORPHY_STORAGE_SHM type
        'storage' => PHPMORPHY_STORAGE_MEM,
        // Extend graminfo for getAllFormsWithGramInfo method call
        'with_gramtab' => false,
        // Enable prediction by suffix
        'predict_by_suffix' => true, 
        // Enable prediction by prefix
        'predict_by_db' => true
    );
    
    $words = $this->makeWordsFromText($text);
    $bulk_words = $this->makeBulkWords($words);

    $w = array();
    foreach ($this->dicts as $dict) {
        // Create descriptor for dictionary located in $dir directory with russian language
        $dict_bundle = new phpMorphy_FilesBundle($this->dict_dir, $dict);
        // Create phpMorphy instance
        $morphy = new phpMorphy($dict_bundle, $opts);
        $tmp = $morphy->getBaseForm($bulk_words);
        $w = array_merge_recursive($w, $tmp);
    }
    return $w;
}

public function getStemmer()
{
    include_once('stemmer.class.php');
    return $stemmer = new Lingua_Stem_Ru();
}

public function Set($key, $value, $escape = false)
{
    if ($escape) {
        $this->{$key} = $this->modx->db->escape($value);
    } else {
        $this->{$key} = $value;
    }
}

public function Get($key, $default = '')
{
    return $this->{$key} ? $this->{$key} : $default ;
}

public function makeSearch()
{
    //возвращаем id ресурсов, отобранные по полнотекстовому поиску и отсортированные по релевантности
    $ids = array();
    $sql = $this->makeSearchSQL();
    //$sql2 = $this->makeSearchSQL('addlike');
    if ($this->params['debug'] == '1') {
        echo $sql . '<hr>';
        //echo $sql2 . '<hr>';
    }
    if ($sql != '') {
        $q = $this->modx->db->query($sql);
        while ($row = $this->modx->db->getRow($q)) {
            $ids[] = $row['docid'];
        }
    }
    if ($this->params['debug'] == '1') {
        echo 'найдены ' . implode(',', $ids) . '<hr>';
    }
    if (empty($ids)) {//ничего не найдено, возможно требуется дополнительный поиск по like
        $sql = $this->makeSearchSQL('addlike');
        if ($this->params['debug'] == '1') {
            echo $sql . '<hr>';
        }
        if ($sql != '') {
            $q = $this->modx->db->query($sql);
            while ($row = $this->modx->db->getRow($q)) {
                $ids[] = $row['docid'];
            }
        }
        if ($this->params['debug'] == '1') {
            echo 'найдены ' . implode(',', $ids) . '<hr>';
        }
    }
    return $ids;
}

public function makeSearchSQL($type = 'fulltext')
{
    switch ($type) {
        case 'fulltext':
            $sql = $this->buildFulltextSQL();
            break;
        case 'addlike':
            $sql = $this->buildAddLikeSQL();
        default:
            break;
    }
    return $sql;
}

public function buildFulltextSQL()
{
    $sql = '';
    if ($this->original != '' || !empty($this->baseform_search_words)) {
        $txt_original = $this->original;
        $search = $txt_original . ' ' . implode(' ', $this->baseform_search_words);
        $sql = "SELECT docid, IF(`pagetitle` LIKE '%" . $txt_original . "%', 2, 0) as pt, IF(`" . $this->ext_content_field . "` LIKE '%" . $txt_original . "%', 1, 0) as ct, (MATCH(" . $this->ext_content_field . "," . $this->ext_content_index_field . ") AGAINST('" . $search . "')) as rel FROM " . $this->search_table . " WHERE IF(`pagetitle` LIKE '%" . $txt_original . "%', 2, 0)>0 OR IF(`" . $this->ext_content_field . "` LIKE '%" . $txt_original . "%', 1, 0)>0 OR (MATCH(" . $this->ext_content_field . "," . $this->ext_content_index_field . ") AGAINST('" . $search . "')) > " . $this->params['rel'] . " ORDER BY pt DESC, (MATCH(" . $this->ext_content_field . "," . $this->ext_content_index_field . ") AGAINST('" . $search . "')) DESC, ct DESC";
    }
    return $sql;
}

public function buildAddLikeSQL()
{
    //ищем вхождение всех слов в заголовок либо в поле content_with_tv
    $sql = '';
    $addPagetitle = $this->makeAddLikeCond('pagetitle', ' ');
    $addContent = $this->makeAddLikeCond($this->ext_content_field, 'OR');
    if ($addPagetitle != '' && $addContent != '') {
        $sql = "SELECT docid, IF(" . $addPagetitle . ", 2,0) as rel FROM " . $this->search_table . " WHERE " . $addPagetitle . " " . $addContent . " ORDER BY rel DESC";
    }
    return $sql;
} 

public function makeStringFromQuery($q, $serapator = ',', $field = 'id')
{
    $out = array();
    while ($row = $this->modx->db->getRow($q)) {
        $out[] = $row[$field];
    }
    return implode($serapator, $out);
}

public function getSearchResultInfo()
{
    $out = '';
    $DL_id = isset($this->params['id']) && !empty($this->params['id']) ? $this->params['id'] . '.' : '';
    $count = (int)$this->modx->getPlaceholder($DL_id . 'count');
    $display = (int)$this->modx->getPlaceholder($DL_id . 'display');
    $current = (int)$this->modx->getPlaceholder($DL_id . 'current');
    if (!$current) $current = 1;
    $from = $to = 0;
    if ($count > 0) {
        $from = ($current - 1) * $this->params['display'] + 1;
        $to = $from - 1 + $display;
        $out .= $this->parseTpl(
                     array('[+stat_request+]', '[+stat_total+]', '[+stat_display+]', '[+stat_from+]', '[+stat_to+]'),
                     array($this->Get('txt_original'), $count, $display, $from, $to),
                     $this->params['statTpl']
                    );
    }
    $this->setPlaceholders(
        array(
            'stat_total' => $count, 
            'stat_display' => $display,
            'stat_from' => $from,
            'stat_to' => $to,
            'stat_tpl' => $out,
            'stat_request' => $this->Get('txt_original')
        )
    );
    return empty($this->params['api']) ? $out : '';
}

public function parseTpl($arr1, $arr2, $tpl)
{
    return str_replace($arr1, $arr2, $tpl);
}

public function sanitarTag($data)
{
        return is_scalar($data) ? str_replace(
            array('[', '%5B', ']', '%5D', '{', '%7B', '}', '%7D'),
            array('&#91;', '&#91;', '&#93;', '&#93;', '&#123;', '&#123;', '&#125;', '&#125;'),
            htmlspecialchars($data, ENT_COMPAT, 'UTF-8', false)
        ) : '';
}

public function setPlaceholders($data = array())
{
    if (is_array($data)) {
        foreach ($data as $name => $value) {
            $this->modx->setPlaceholder($name, $value);
        }
    }
}

public function parseNoresult($noResult)
{
    return $this->parseTpl(array('[+stat_request+]'), array($this->Get('txt_original')), $noResult);
}


public function makeAddLikeCond($search_field = 'pagetitle', $separator = 'AND', $inner_separator = 'AND')
{
    $out = '';
    foreach ($this->search_words as $word) {
        $word = mb_strtolower($word, "UTF-8");
        $mysqlVersion = !empty($this->params['mysqlVersion']) ? $this->params['mysqlVersion'] : 5;
        $sql = $mysqlVersion == 8 ? 
            " LOWER(`" . $search_field . "`) REGEXP '\\\\b(" . $word . ")\\\\b'" : 
            " LOWER(`" . $search_field . "`) REGEXP '[[:<:]]" . $word . "[[:>:]]'";
        $tmp[] = $sql;
    }
    if (!empty($tmp)) {
        $out = implode(' ' . trim($inner_separator) . ' ', $tmp);
    }
    if (!empty($out)) {
        $out = ' ' . $separator . ' (' . $out . ')';
    }
    return $out;
}

}//class end
