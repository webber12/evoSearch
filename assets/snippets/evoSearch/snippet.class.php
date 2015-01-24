<?php

if(file_exists(MODX_BASE_PATH.'assets/snippets/DocLister/lib/DLTemplate.class.php')){
    include_once(MODX_BASE_PATH.'assets/snippets/DocLister/lib/DLTemplate.class.php');
}

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


public function __construct($modx, $params, $min_length = 2, $ext_content_field = 'content_with_tv', $ext_content_index_field = 'content_with_tv_index') {
    $this->modx = $modx;
    $this->params = $params;
    $this->phpmorphy_dir = MODX_BASE_PATH . 'assets/libs/phpmorphy';
    $this->dict_dir = $this->phpmorphy_dir . '/dicts';
    $this->min_length = $min_length;
    $this->dicts = array('rus', 'eng');
    $this->id = $this->params['id'];
    $this->content_table = $this->modx->getFullTableName("site_content");
    $this->ext_content_field = $ext_content_field;
    $this->ext_content_index_field = $ext_content_index_field;
    $this->stemmer = $this->getStemmer();
}

/**
 * Возвращает все словоформы слов поискового запроса
 *
 * @param string $text
 * @return array
 */
public function Words2AllForms($text) {
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
        $tmp = $morphy->getAllForms($bulk_words);
        $w = array_merge_recursive($w, $tmp);
    }
    return $w;
}

public function makeWordsFromText($text) {
    $words = array();
    $words = preg_replace('#\[.*\]#isU', '', $text);
    $words = str_replace(array('&ndash;', '&raquo;', '&laquo;', '&darr;', '&rarr;'), array('', '', '', '', ''), $words);
    $words = preg_split('#\s|[,.:;!?"\'()]#', $text, -1, PREG_SPLIT_NO_EMPTY);
    return $words;
}

public function makeBulkWords($words, $upper = true) {
    $bulk_words = array();
    foreach ($words as $v) {
        if (strlen($v) > $this->min_length) {
            $bulk_words[] = $upper ? strtoupper($v) : $v;
        }
    }
    return $bulk_words;
}

public function getStemmer() {
    include_once('stemmer.class.php');
    return $stemmer = new Lingua_Stem_Ru();
}

public function Set($key, $value, $escape = false) {
    if ($escape) {
        $this->{$key} = $this->modx->db->escape($value);
    } else {
        $this->{$key} = $value;
    }
}

public function Get($key, $default = '') {
    return $this->{$key} ? $this->{$key} : $default ;
}

public function makeSearchSQL ($txt_original = '') {
    $txt_original = ($txt_original == '' ? $this->Get('txt_original') : $txt_original);
    $this->txt_ext_array = $this->Words2AllForms($txt_original);
    $this->txt_ext = '';
    foreach ($this->txt_ext_array as $v) {
        if (is_array($v)) {
            $this->txt_ext .= ' ' . implode(" ", $v);
        } else {
            $this->txt_ext .= ' ' . $v;
        }
    }
    $query = $this->buildFulltextSQL ();
    //print_r($sql);
    return $query;
}

public function buildFulltextSQL ($txt_original = '', $txt_ext = '') {
    $txt_original = ($txt_original == '' ? $this->Get('txt_original') : $txt_original);
    $tmp = array();
    $txt_ext = ($txt_ext == '' ? $this->Get('txt_ext') : $txt_ext);
    if ($txt_ext == '') {
        $tmp['sql'] = "SELECT id, (MATCH(`pagetitle`) AGAINST('" . $txt_original . "') * 5 + MATCH (`" . $this->ext_content_field . "`, `" . $this->ext_content_index_field . "`) AGAINST ('" . $txt_original . "')) as rel FROM " . $this->content_table . " WHERE `searchable`='1' AND (MATCH(`pagetitle`) AGAINST('" . $txt_original . "') > " . $this->params['rel'] . " OR MATCH (`" . $this->ext_content_field . "`, `" . $this->ext_content_index_field . "`) AGAINST ('" . $txt_original . "') > " . $this->params['rel'] . ") ORDER BY rel DESC";
        $tmp['selectFields'] = "c.*, (MATCH(c.pagetitle) AGAINST('" . $txt_original . "') * 5 + MATCH (c." . $this->ext_content_field . ", c." . $this->ext_content_index_field . ") AGAINST ('" . $txt_original . "')) as rel";
        $tmp['addWhereList'] = "c.searchable='1' AND (MATCH(c.pagetitle) AGAINST('" . $txt_original . "')> " . $this->params['rel'] . " OR MATCH (c." . $this->ext_content_field . ", c." . $this->ext_content_index_field . ") AGAINST ('" . $txt_original . "') > " . $this->params['rel'] . ")";
        $tmp['orderBy'] = 'rel DESC';
    } else {
        $tmp['sql'] = "SELECT id, (MATCH(`pagetitle`) AGAINST('" . $txt_original . " " . $txt_ext . "') * 5 + MATCH (`" . $this->ext_content_field . "`, `" . $this->ext_content_index_field . "`) AGAINST ('" . $txt_original . " " . $txt_ext . "')) as rel FROM " . $this->content_table . " WHERE `searchable`='1' AND (MATCH(`pagetitle`) AGAINST('" . $txt_original . " " . $txt_ext . "') > " . $this->params['rel'] . " OR MATCH (`" . $this->ext_content_field . "`, `" . $this->ext_content_index_field . "`) AGAINST ('" . $txt_original . " " . $txt_ext."') > " . $this->params['rel'] . ") ORDER BY rel DESC";
        $tmp['selectFields'] = "c.*, (MATCH(c.pagetitle) AGAINST('" . $txt_original . " " . $txt_ext . "') * 5 + MATCH (c." . $this->ext_content_field . ", c." . $this->ext_content_index_field . ") AGAINST ('" . $txt_original . " " . $txt_ext . "')) as rel";
        $tmp['addWhereList'] = "c.searchable='1' AND (MATCH(c.pagetitle) AGAINST('" . $txt_original . " " . $txt_ext . "') > " . $this->params['rel'] . " OR MATCH (c." . $this->ext_content_field . ", c." . $this->ext_content_index_field . ") AGAINST ('" . $txt_original . " " . $txt_ext."') > " . $this->params['rel'] . ")";
        $tmp['orderBy'] = 'rel DESC';
    }
    return $tmp;
}

public function makeStringFromQuery ($q, $serapator = ',', $field = 'id') {
    $out = array();
    while ($row = $this->modx->db->getRow($q)) {
        $out[] = $row[$field];
    }
    return implode($serapator, $out);
}

public function makeAddQueryForEmptyResult($bulk_words_original, $txt_original = '', $worker = 'DocLister') {
    $output = '';
    $txt_original = ($txt_original == '' ? $this->Get('txt_original') : $txt_original);
    $this->params['documents'] = ''; //очищаем список документов, если там что-то было
    $this->params['addWhereList'] = 'c.searchable=1'; //условия поиска только среди доступных для поиска
    //$this->params['sortType'] = 'doclist'; - тут будем сортировать по умолчанию - по дате создания/публикации

    //берем id всех документов сайта
    $q = $this->modx->db->query("SELECT id FROM " . $this->content_table . " WHERE `searchable`='1' AND `deleted`='0' AND `published`='1'");
    $documents = $this->makeStringFromQuery($q);
    $this->params['documents'] = $documents;

    $s = implode(",", $bulk_words_original);
    if ($s != '') {//если в поиске есть хоть одно значимое слово, то будем искать
        $this->params['filters'] = 'OR(content:pagetitle:eq:' . $txt_original . ';content:pagetitle:like-r:' . $txt_original . ';content:pagetitle:like-l:' . $txt_original . ';content:pagetitle:like: ' . $txt_original . ' ;content:pagetitle:against:' . $txt_original . ';content:' . $this->ext_content_field . ',' . $this->ext_content_index_field . ':against:' . $txt_original . ')';
        //$output .= $this->modx->runSnippet($worker, $this->params);
    }
    //return $this->params;
}

public function getSearchResultInfo() {
    $out = '';
    $count = $this->modx->getPlaceholder('count');
    $display = $this->modx->getPlaceholder('display');
    $current = $this->modx->getPlaceholder('current');
    $from = ($current - 1) * $this->params['display'] + 1;
    $to = $from - 1 + $display;
    if ($count && $count != '0' && $count != '') {
        $out .= $this->parseTpl(
                     array('stat_request', 'stat_total', 'stat_display', 'stat_from', 'stat_to'),
                     array($this->Get('txt_original'), $count, $display, $from, $to),
                     $this->params['statTpl']
                    );
    }
    $this->setPlaceholders(
        array(
            'stat_total' => $count,
            'stat_display' => $display,
            'stat_from' => $from,
            'stat_to' => $to
        )
    );
    return $out;
}

public function parseTpl($arr1, $arr2, $tpl) {
    if(class_exists('DLTemplate')){
        $tplObj = DLTemplate::getInstance($this->modx);
        $html = $tplObj->getChunk($tpl);
        if(empty($html)){
            $html = $tpl;
        }
        $out = $tplObj->parseChunk('@CODE: '.$html, array_combine($arr1, $arr2));
    }else{
        foreach($arr1 as &$val){
            $val = '[+'.$val.'+]';
        }
        $out = str_replace($arr1, $arr2, $tpl);
    }
    return $out;
}

public function sanitarTag($data) {
        return is_scalar($data) ? str_replace(
            array('[', '%5B', ']', '%5D', '{', '%7B', '}', '%7D'),
            array('&#91;', '&#91;', '&#93;', '&#93;', '&#123;', '&#123;', '&#125;', '&#125;'),
            htmlspecialchars($data, ENT_COMPAT, 'UTF-8', false)
        ) : '';
}

public function setPlaceholders($data = array()) {
    if (is_array($data)) {
        foreach ($data as $name => $value) {
            $this->modx->setPlaceholder($name, $value);
        }
    }
}

public function parseNoresult($noResult) {
    return $this->parseTpl(array('stat_request'), array($this->Get('txt_original')), $noResult);
}

}//class end