<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

class evoSearchPlugin {

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
    $this->dicts = $this->getDicts($this->params['dicts']);
    $this->id = $this->params['id'];
    $this->content_table = $this->modx->getFullTableName("site_content");
    $this->ext_content_field = $ext_content_field;
    $this->ext_content_index_field = $ext_content_index_field;
}

public function getDicts($dicts) {
    return explode(',', trim($dicts));
}

public function Words2BaseForm($text) {
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

    $fullList = array();
    foreach ($this->dicts as $dict) {
        // Create descriptor for dictionary located in $dir directory with russian language
        $dict_bundle = new phpMorphy_FilesBundle($this->dict_dir, $dict);
        
        // Create phpMorphy instance
        $morphy = new phpMorphy($dict_bundle, $opts);
        
        //get base form for all words
        $base_form = $morphy->getBaseForm($bulk_words);
        
        if ( is_array($base_form) && count($base_form) ) {
            foreach ( $base_form as $k => $v ) {
                if ( is_array($v) ) {
                    foreach ( $v as $v1 ) {
                        if ( strlen($v1) > $this->min_length ) {
                            $fullList[$v1] = 1;
                        }
                    }
                }
            }
        }
    }
    $words = join(' ', array_keys($fullList));
    return $words;
}


public function makeSQLForSelectWords () {
    $where = ' AND id IN(' . $this->id . ')';
    $order = ' ORDER BY id ASC ';
    $limit = ' LIMIT 0,1';
    if (isset($this->params['reindex']) && (int)$this->params['reindex'] != 0) {
        $where = '';
        $limit = ' LIMIT ' . (isset($this->params['offset']) ? (int)$this->params['offset'] : 0) . ',' . (isset($this->params['rowsperonce']) ? (int)$this->params['rowsperonce'] : 1);
    }
    $where .= ($this->params['unpublished'] == '0' ? ' AND published=1 ' : '');
    $where .= ($this->params['deleted'] == '0' ? ' AND deleted=0 ' : '');
    $where .= ($this->params['excludeTmpls'] != '' ? ' AND template NOT IN(' . $this->params['excludeTmpls'] . ') ' : '');
    $where .= ($this->params['excludeIDs'] != '' ? ' AND id NOT IN(' . $this->params['excludeIDs'] . ') ' : '');
    $sql = "SELECT id,pagetitle,longtitle,description,introtext,content FROM " . $this->content_table . " WHERE searchable = 1" . $where . $order . $limit;
    return $sql;
}

public function emptyExcluded() {
    if (isset($this->params['reindex']) && (int)$this->params['reindex'] != 0) {
        $where = '';
        $where .= ($this->params['excludeTmpls'] != '' ? ' template IN(' . $this->params['excludeTmpls'] . ') ' : '');
        $where .= ($this->params['excludeIDs'] != '' ? ($where != '' ? ' OR ' : '') . ' id IN(' . $this->params['excludeIDs'] . ') ' : '');
        if ($where != '') {
            $upd = $this->modx->db->update(
                array($this->ext_content_field => '', $this->ext_content_index_field => ''),
                $this->content_table,
                $where
            );
        }
    }
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

public function injectTVs($id, $content) {
    $tvs = '';
    if ($this->params['TvNames'] != '') {
        $TvNames = explode(',', $this->params['TvNames']);
        $TvValues = $this->modx->getTemplateVarOutput($TvNames, $id);
        if(is_array($TvValues) && !empty($TvValues)) {
            $tvs = $this->modx->stripTags(implode(', ', $TvValues));
        }
    }
    $content .= ($tvs != '' ? ' '.$tvs : '');
    return $content;
}

private function checkColumnExists($columnname, $table) {
    $columns = $this->modx->db->getTableMetaData($table);
    return isset($columns[$columnname]);
}

private function createSearchColumns() {
    $sql = "ALTER TABLE " . $this->content_table . " ADD `" . $this->ext_content_field . "` MEDIUMTEXT NOT NULL";
    $this->modx->db->query($sql);
    $sql = "ALTER TABLE " . $this->content_table . " ADD `" . $this->ext_content_index_field . "` MEDIUMTEXT NOT NULL";
    $this->modx->db->query($sql);
    $sql = "ALTER TABLE " . $this->content_table . " ADD FULLTEXT content_index (`" . $this->ext_content_field . "`, `" . $this->ext_content_index_field . "`)";
    $this->modx->db->query($sql);
    $sql = "ALTER TABLE " . $this->content_table . " ADD FULLTEXT(`pagetitle`)";
    $this->modx->db->query($sql);
}

public function prepareRun() {
    if (!$this->checkColumnExists($this->ext_content_field, $this->content_table)) {
        $this->createSearchColumns();
    }
}

}
