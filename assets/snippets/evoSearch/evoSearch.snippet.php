<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$documents = '';
$output = '';
$txt_original = '';

include_once('snippet.class.php');
$eSS = new evoSearchSnippet($modx, $params);

if (isset($_GET['search']) && $_GET['search'] != '') {
    $txt_original = $eSS->modx->db->escape($_GET['search']);
	$eSS->Set('txt_original', $_GET['search'], true);
    //echo '<br>'.$txt_original.'<br>';
    $w = $eSS->Words2AllForms($eSS->Get('txt_original'));
    $txt = '';
    foreach ($w as $v) {
        $txt .= ' ' . implode(" ", $v);
    }
    if ($txt == '') {
        $sql = "SELECT *, (MATCH(`pagetitle`) AGAINST('" . $eSS->Get('txt_original') . "') * 5 + MATCH (`" . $eSS->ext_content_field . "`, `" . $eSS->ext_content_index_field . "`) AGAINST ('" . $eSS->Get('txt_original') . "')) as rel FROM " . $eSS->content_table . " WHERE `searchable`='1' AND (MATCH(`pagetitle`) AGAINST('" . $eSS->Get('txt_original') . "')>2 OR MATCH (`" . $eSS->ext_content_field . "`, `" . $eSS->ext_content_index_field . "`) AGAINST ('" . $eSS->Get('txt_original') . "') > 2) ORDER BY rel DESC";
    } else {
        $sql = "SELECT *, (MATCH(`pagetitle`) AGAINST('" . $eSS->Get('txt_original') . " " . $txt . "') * 5 + MATCH (`" . $eSS->ext_content_field . "`, `" . $eSS->ext_content_index_field . "`) AGAINST ('" . $eSS->Get('txt_original') . " ".$txt."')) as rel FROM " . $eSS->content_table . " WHERE `searchable`='1' AND (MATCH(`pagetitle`) AGAINST('" . $eSS->Get('txt_original') . " ".$txt."')>2 OR MATCH (`" . $eSS->ext_content_field . "`, `" . $eSS->ext_content_index_field . "`) AGAINST ('" . $eSS->Get('txt_original') . " " . $txt."') > 2) ORDER BY rel DESC";
    }
    //echo $sql;
    $q = $eSS->modx->db->query($sql);
    while($row = $modx->db->getRow($q)) {
        $documents .= $row['id'].',';
    }
    $documents = substr($documents, 0, -1);

    $eSS->params['documents'] = $documents;
    $worker = isset($eSS->params['worker']) ? $eSS->params['worker'] : "DocLister";

    $words_original = $eSS->makeWordsFromText($eSS->Get('txt_original'));
    $bulk_words_original = $eSS->makeBulkWords($words_original, false);
    $bulk_words_stemmer = array();
    foreach ($bulk_words_original as $v) {
        $bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
    }
    //print_r($bulk_words_stemmer);

    if ($documents == '') {
        if ($worker == 'DocLister') {
            $eSS->params['documents'] = '';
            $eSS->params['addWhereList'] = 'c.searchable=1';
            $q = $eSS->modx->db->query("SELECT id FROM " . $eSS->content_table . " WHERE `searchable`='1' AND `deleted`='0' AND `published`='1' ORDER BY createdon DESC");
            while ($row = $eSS->modx->db->getRow($q)) {
                $documents .= $row['id'].',';
            }
            $eSS->params['documents'] = substr($documents, 0, -1);
            $eSS->params['sortType'] = 'doclist';
            $s = implode(",", $bulk_words_original);
            if ($s != '') {
                $eSS->params['filters'] = 'OR(content:pagetitle:eq:' . $eSS->Get('txt_original') . ';content:pagetitle:like-r:' . $eSS->Get('txt_original') . ';content:pagetitle:like-l:' . $eSS->Get('txt_original') . ';content:pagetitle:like: ' . $eSS->Get('txt_original') . ' ;content:pagetitle:against:' . $eSS->Get('txt_original') . ';content:' . $eSS->ext_content_field . ',' . $eSS->ext_content_index_field . ':against:' . $eSS->Get('txt_original') . ' ' . strtoupper($eSS->Get('txt_original')) . ')';
                //print_r($eSS->params);
                $output .= $eSS->modx->runSnippet($worker, $eSS->params);
            }
        }
    } else {
        if ($worker == 'DocLister') {
            $eSS->params['sortType'] = "doclist";
            $eSS->params['idType'] = "documents";
            $eSS->params['addWhereList'] = 'c.searchable=1';
        } else if ($worker == 'Ditto') {
            $eSS->params['extenders'] = 'nosort';
            $eSS->params['where'] = 'searchable=1';
        } else {}
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    }

}

//print_r($params);
echo $output != '' ? $output : 'Ничего не найдено. Смягчите условия поиска';
