<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$documents = '';
$output = '';
$noResult = isset($params['noResult']) ? $params['noResult'] : 'По запросу <u>[+stat_request+]</u> ничего не найдено. Смягчите условия поиска';
$txt_original = '';

include_once('snippet.class.php');
$eSS = new evoSearchSnippet($modx, $params);

if (isset($_GET['search']) && $_GET['search'] != '') {
    $eSS->params['display'] = isset($eSS->params['display']) ? $eSS->params['display'] : "20";
    $eSS->params['paginate'] = isset($eSS->params['paginate']) ? $eSS->params['paginate'] : "pages";
    $eSS->params['show_stat'] = isset($eSS->params['show_stat']) ? $eSS->params['show_stat'] : "1";
    $eSS->params['addLikeSearch'] = isset($eSS->params['addLikeSearch']) ? $eSS->params['addLikeSearch'] : "0";
    $eSS->params['addLikeSearchType'] = isset($eSS->params['addLikeSearchType']) ? $eSS->params['addLikeSearchType'] : "exact";
    $eSS->params['addLikeSearchLength'] = isset($eSS->params['addLikeSearchLength']) ? $eSS->params['addLikeSearchLength'] : "3";
    $eSS->params['addSearch'] = isset($eSS->params['addSearch']) ? $eSS->params['addSearch'] : "1";
    $eSS->params['extract'] = isset($eSS->params['extract']) ? $eSS->params['extract'] : "1";
    $eSS->params['statTpl'] = isset($eSS->params['statTpl']) ? $eSS->params['statTpl'] : '<div class="evoSearch_info">По запросу <b>[+stat_request+]</b> найдено всего <b>[+stat_total+]</b>. Показано <b>[+stat_display+]</b>, c [+stat_from+] по [+stat_to+]</div>';
    $worker = isset($eSS->params['worker']) ? $eSS->params['worker'] : "DocLister";
    $eSS->params['rel'] = isset($eSS->params['rel']) ? str_replace(',', '.', round($eSS->params['rel'], 2)) : str_replace(',', '.', 0.01);

    $eSS->Set('txt_original', $eSS->sanitarTag($_GET['search']), true);
    $modx->setPlaceholder('stat_request', $eSS->Get('txt_original'));
    //echo '<br>'.$eSS->Get('txt_original').'<br>';

    $query = $eSS->makeSearchSQL();
    //echo $sql;
    if ($worker != "DocLister") {
        $q = $eSS->modx->db->query($query['sql']);
        $documents = $eSS->makeStringFromQuery($q);
        $eSS->params['documents'] = $documents;
    }

    $words_original = $eSS->makeWordsFromText($eSS->Get('txt_original'));
    $bulk_words_original = $eSS->makeBulkWords($words_original, false);

    $eSS->bulk_words_stemmer = array();
    foreach ($bulk_words_original as $v) {
        $eSS->bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
    }

    if ($worker == 'DocLister') {
        $eSS->params['parents'] = isset($eSS->params['parents']) ? $eSS->params['parents'] : "0";
        $eSS->params['depth'] = "7";
        $eSS->params['showParent'] = "1";
        $eSS->params['addWhereList'] = isset($eSS->params['addWhereList']) && !empty($eSS->params['addWhereList']) ? $eSS->params['addWhereList'] . ' AND ' . $query['addWhereList'] : $query['addWhereList'];
        $eSS->params['selectFields'] = $query['selectFields'];
        $eSS->params['orderBy'] = $query['orderBy'];
        if ($eSS->params['extract'] == '1') {
            $eSS->params['prepare'] = array($eSS, 'prepareExtractor');
        }
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    } else if ($worker == 'Ditto') {
        $eSS->params['extenders'] = 'nosort';
        $eSS->params['where'] = 'searchable=1';
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    } else {
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    }

    if ($output == '' && $worker == 'DocLister' && $eSS->params['addSearch'] != '0') {
        $eSS->makeAddQueryForEmptyResult($bulk_words_original);
        if ($eSS->params['extract'] == '1') {
            $eSS->params['prepare'] = array($eSS, 'prepareExtractor');
        }
        $eSS->params['addWhereList'] = isset($params['addWhereList']) && !empty($params['addWhereList']) ? $params['addWhereList'] . ' AND ' . $eSS->params['addWhereList'] : $eSS->params['addWhereList'];
        $output .= $eSS->modx->runSnippet($worker, $eSS->params);
    }
    if ($eSS->params['show_stat'] == '1'  && $worker == 'DocLister') {
        $output = $eSS->getSearchResultInfo() . $output;
    }

}

echo $output != '' ? $output : (!isset($_REQUEST['search']) ? '' : '<div class="noResult">' . $eSS->parseNoresult($noResult) . '</div>');
