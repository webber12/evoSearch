<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$documents = '';
$output = '';
$txt_original = '';

include_once('snippet.class.php');
$eSS = new evoSearchSnippet($modx, $params);

if (isset($_GET['search']) && $_GET['search'] != '') {
    $eSS->Set('txt_original', $_GET['search'], true);
    //echo '<br>'.$eSS->Get('txt_original').'<br>';

    $sql = $eSS->makeSearchSQL();
    //echo $sql;
    $q = $eSS->modx->db->query($sql);
    $documents = $eSS->makeStringFromQuery($q);
    $eSS->params['documents'] = $documents;
    $worker = isset($eSS->params['worker']) ? $eSS->params['worker'] : "DocLister";

    $words_original = $eSS->makeWordsFromText($eSS->Get('txt_original'));
    $bulk_words_original = $eSS->makeBulkWords($words_original, false);

    //TODO stemmer for extractor
    $bulk_words_stemmer = array();
    foreach ($bulk_words_original as $v) {
        $bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
    }
    //end TODO

    if ($documents == '') {
        if ($worker == 'DocLister') {
            $output .= $eSS->makeAddQueryForEmptyResult($bulk_words_original);
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
echo $output != '' ? $output : (!isset($_REQUEST['search']) ? '' : 'Ничего не найдено. Смягчите условия поиска');
