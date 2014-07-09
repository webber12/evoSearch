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
 * Подсветка слов поискового запроса
 *
 * @param string $whereText
 * @param string $whatText
 * @return string
 */
public function Highlight($whereText, $whatText) {

    $highlightWords = $highlightWordsRepl = array();
    $highlightWordsT = Words2AllForms($whatText);
    
    foreach ( $highlightWordsT as $k => $v ) {
        if ( !$v ) {
            $highlightWords[]  = "#\b($k)\b#isU";
            $highlightWordsRepl[] = '[highlight]\\1[/highlight]';
        } else {
            foreach ( $v as $v1 ) {
                $highlightWords[]  = "#\b($v1)\b#isU";
                $highlightWordsRepl[] = '[highlight]\\1[/highlight]';
            }
        }
    }
    return $message['message_text'] = preg_replace(array_reverse($highlightWords), '[highlight]$1[/highlight]', $whereText);
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
    $words = str_replace(array('&ndash;','&raquo;','&laquo;','&darr;','&rarr;'), array('','','','',''), $words);
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


}//class end
