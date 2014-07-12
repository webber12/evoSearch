<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$documents = '';
$output = '';
$noResult = isset($params['noResult']) ? $params['noResult'] : 'Ничего не найдено. Смягчите условия поиска';
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

    $eSS->bulk_words_stemmer = array();
    foreach ($bulk_words_original as $v) {
        $eSS->bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
    }

    if ($documents == '') {
        if ($worker == 'DocLister') {
            if (!isset($eSS->params['addSearch']) || $eSS->params['addSearch'] != '0') {
                $eSS->makeAddQueryForEmptyResult($bulk_words_original);
                if (isset($eSS->params['extract']) && $eSS->params['extract'] == '1') {
                    $eSS->params['prepare'] = array($eSS,'prepareExtractor');
                }
                $output .= $eSS->modx->runSnippet($worker, $eSS->params);
            }
        }
    } else {
        if ($worker == 'DocLister') {
            $eSS->params['sortType'] = "doclist";
            $eSS->params['idType'] = "documents";
            $eSS->params['addWhereList'] = 'c.searchable=1';
            if (isset($eSS->params['extract']) && $eSS->params['extract'] == '1') {
                $eSS->params['prepare'] = array($eSS,'prepareExtractor');
            }
        } else if ($worker == 'Ditto') {
            $eSS->params['extenders'] = 'nosort';
            $eSS->params['where'] = 'searchable=1';
        } else {}
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    }

}

//print_r($params);
echo $output != '' ? $output : (!isset($_REQUEST['search']) ? '' : '<div class="noResult">'.$noResult.'</div>');
