<?php
if (!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

class evoSearchPlugin
{

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

    public function __construct($modx, $params, $min_length = 2, $ext_content_field = 'content_with_tv', $ext_content_index_field = 'content_with_tv_index', $search_table = 'evosearch_table', $content_table = 'site_content', $search_fields = 'pagetitle,longtitle,description,introtext,content')
    {
        $this->modx = $modx;
        $this->params = $params;
        $this->phpmorphy_dir = MODX_BASE_PATH . 'assets/lib/phpmorphy';
        $this->dict_dir = $this->phpmorphy_dir . '/dicts';
        $this->min_length = $min_length;
        $this->dicts = $this->getDicts($this->params['dicts']);
        $this->id = $this->params['id'];
        $this->content_table_name = $content_table;
        $this->content_table = $this->modx->getFullTableName($this->content_table_name);
        $this->ext_content_field = $ext_content_field;
        $this->ext_content_index_field = $ext_content_index_field;
        $this->search_table = $this->modx->getFullTableName($search_table);
        $this->search_fields = $this->cleanIn($search_fields);
    }

    public function getDicts($dicts)
    {
        return explode(',', $this->cleanIn($dicts));
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

        $fullList = array();
        foreach ($this->dicts as $dict) {
            // Create descriptor for dictionary located in $dir directory with russian language
            $dict_bundle = new phpMorphy_FilesBundle($this->dict_dir, $dict);

            // Create phpMorphy instance
            $morphy = new phpMorphy($dict_bundle, $opts);

            //get base form for all words
            $base_form = array();
            foreach ($bulk_words as $bulk_word) {
                $base_form[] = $morphy->getBaseForm($bulk_word);
            }

            if (is_array($base_form) && count($base_form)) {
                foreach ($base_form as $k => $v) {
                    if (is_array($v)) {
                        foreach ($v as $v1) {
                            if (strlen($v1) > $this->min_length) {
                                $fullList[] = $v1;
                            }
                        }
                    }
                }
            }
        }
        //$words = join(' ', array_keys($fullList));
        return implode(' ', $fullList);
    }

    public function cleanIn($text, $quote = false)
    {
        if ($quote) {
            return $text;
        }
        return str_replace(', ', ',', trim($text));
    }


    public function makeSQLForSelectWords()
    {
        if (!isset($this->params['reindex']) || (int)$this->params['reindex'] == 0) {
            $where = ' AND id IN(' . $this->id . ')';
            $limit = ' LIMIT 0,1';
        } else {
            $where = '';
            if (!isset($this->params['rowsperonce'])) {
                if (!isset($this->params['offset'])) {
                    $limit = ' LIMIT 0,1';
                } else {
                    $limit = ' LIMIT ' . ((int)$this->params['offset']) . ',1';
                }
            } else {
                if (!isset($this->params['offset'])) {
                    $limit = ' LIMIT 0,' . ((int)$this->params['rowsperonce']);
                } else {
                    $limit = ' LIMIT ' . ((int)$this->params['offset']) . ',' . ((int)$this->params['rowsperonce']);
                }
            }
        }
        if ($this->params['unpublished'] == '0') {
            $where .= ' AND published=1 ';
        }
        if ($this->params['deleted'] == '0') {
            $where .= ' AND deleted=0 ';
        }
        if ($this->params['excludeTmpls'] != '') {
            $where .= ' AND template NOT IN(' . $this->params['excludeTmpls'] . ') ';
        }
        if ($this->params['excludeIDs'] != '') {
            $where .= ' AND id NOT IN(' . $this->params['excludeIDs'] . ') ';
        }
        return sprintf(
            "SELECT id,%s FROM %s WHERE searchable = 1 %s ORDER BY id ASC %s",
            $this->search_fields,
            $this->content_table,
            $where,
            $limit
        );
    }

    public function emptyExcluded()
    {
        if (!isset($this->params['reindex']) || (int)$this->params['reindex'] == 0) {
            return;
        }
        $ids = array();
        $where = ($this->params['excludeTmpls'] != '' ? ' template IN(' . $this->cleanIn($this->params['excludeTmpls']) . ') ' : '');
        $where = $where . ($this->params['excludeIDs'] != '' ? ($where != '' ? ' OR ' : '') . ' id IN(' . $this->cleanIn($this->params['excludeIDs']) . ') ' : '');
        if ($where != '') {
            $q = $this->modx->db->select("id", $this->content_table, $where);
            while ($row = $this->modx->db->getRow($q)) {
                $ids[] = $row['id'];
            }
        }

        if (empty($ids)) {
            return;
        }

        $this->modx->db->delete(
            $this->search_table,
            "docid IN(" . implode(',', $ids) . ") AND `table`='" . $this->content_table_name . "'"
        );
    }

    public function makeWordsFromText($text)
    {
        $words = preg_replace('#\[.*]#isU', '', $text);
        $words = str_replace(
            array('&ndash;', '&raquo;', '&laquo;', '&darr;', '&rarr;', '&mdash;'),
            array('', '', '', '', '', ''),
            $words
        );
        $words = preg_split('#\s|[,.:;!?"\'()]#', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $words;
    }

    public function makeBulkWords($words, $upper = true)
    {
        $bulk_words = array();
        foreach ($words as $v) {
            if (strlen($v) > $this->min_length) {
                //$bulk_words[] = $upper ? strtoupper($v) : $v;
                $bulk_words[] = $v;
            }
        }
        return $bulk_words;
    }

    public function injectTVs($id, $content)
    {
        $tvs = array();
        if ($this->params['TvNames'] != '') {
            $TvNames = explode(',', $this->params['TvNames']);
            $TvValues = $this->modx->getTemplateVarOutput($TvNames, $id);
            $TvElements = $this->modx->getTemplateVars($TvNames, 'name,elements', $id, 'all');
            if (!empty($TvElements) && !empty($TvValues)) {
                foreach ($TvElements as $k => $el) {
                    $tv_name = $el['name'];
                    //если мы получаем значения TV из дерева с помощью сниппета multiParams, то в поле elements у него будет getParamsFromTree
                    //для таких элементов берем из дерева их истинные значения
                    //для остальных - оставляем оригинальные значения TV
                    if (stristr($el['elements'], 'getParamsFromTree') === FALSE) {
                        $tvs[] = str_replace('||', ' ', $TvValues[$tv_name]);
                    } else {
                        $docs = str_replace('||', ',', $TvValues[$tv_name]);
                        if ($docs && $docs != '') {
                            $q = $this->modx->db->query("SELECT id,pagetitle FROM " . $this->content_table . " WHERE id IN(" . $docs . ")");
                            while ($row = $this->modx->db->getRow($q)) {
                                $tvs[] = $row['pagetitle'];
                            }
                        }
                    }
                }
            }
        }
        $content .= !empty($tvs) ? ' ' . implode(' ', $tvs) . ' ' : '';
        $prepare = $this->invokePrepare([
            'id' => $id, 'content' => $content, 'event' => 'OnAfterInjectTVs'
        ]);
        if (!$prepare || !is_array($prepare) || !isset($prepare['content'])) {
            return $content;
        }
        return $prepare['content'];
    }

    private function checkColumnExists($columnname, $table)
    {
        $columns = $this->modx->db->getTableMetaData($table);
        return isset($columns[$columnname]);
    }

    private function createSearchTable()
    {
        $sql = '
        CREATE TABLE IF NOT EXISTS ' . $this->modx->getFullTableName('evosearch_table') . ' (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `docid` int(10) NOT NULL,
            `table` varchar(255) NOT NULL,
            `pagetitle` varchar(255) NOT NULL,
            `' . $this->ext_content_field . '` mediumtext NOT NULL,
            `' . $this->ext_content_index_field . '` mediumtext NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `docid_table` (`docid`,`table`),
            FULLTEXT KEY `content_index` (`' . $this->ext_content_field . '`,`' . $this->ext_content_index_field . '`),
            FULLTEXT KEY `pagetitle` (`pagetitle`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
    ';
        $this->modx->db->query($sql);
        //$sql = "ALTER TABLE " . $this->content_table . " ADD FULLTEXT(`pagetitle`)";
        //$this->modx->db->query($sql);
    }

    public function prepareRun()
    {
        $this->createSearchTable();
    }

    public function updateSearchTable($fields)
    {
        if (empty($fields)) {
            return false;
        }
        $keys = '`' . implode('`,`', array_keys($fields)) . '`';
        $values = "'" . implode("','", array_values($fields)) . "'";
        $sql = "INSERT INTO " . $this->search_table . " (" . $keys . ") VALUES (" . $values . ") ON DUPLICATE KEY UPDATE `" . $this->ext_content_field . "`='" . $fields[$this->ext_content_field] . "', `" . $this->ext_content_index_field . "`='" . $fields[$this->ext_content_index_field] . "', `pagetitle`='" . $fields['pagetitle'] . "'";

        return $this->modx->db->query($sql);
    }

    public function invokePrepare($data)
    {
        if (!isset($this->params['prepare']) || empty(trim($this->params['prepare']))) {
            return false;
        }

        $prepare = trim($this->params['prepare']);
        if (strpos($prepare, '->') > 0) {
            //вариант className->classMethod
            $prepare = explode('->', $prepare, 2);
        }
        switch (true) {
            case is_callable($prepare):
                return call_user_func($prepare, $data) ?: false;
            case is_scalar($prepare):
                return $this->modx->runSnippet($prepare, array('data' => $data)) ?: false;
        }
        return false;
    }
}
