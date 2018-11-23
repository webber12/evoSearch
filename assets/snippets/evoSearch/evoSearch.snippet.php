<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$documents = '';
$output = '';
$noResult = isset($params['noResult']) ? $params['noResult'] : 'По запросу <u>[+stat_request+]</u> ничего не найдено. Смягчите условия поиска';
$txt_original = '';

include_once('snippet.class.php');
$min_length = (isset($params['minlength']) && (int)$params['minlength'] > 0) ? (int)$params['minlength'] : 2;
$eSS = new evoSearchSnippet($modx, $params, $min_length);

if (isset($_GET[$eSS->search_field]) && $_GET[$eSS->search_field] != '') {
    $eSS->init();
    $eSS->prepareWords();
    $modx->setPlaceholder('stat_request', $eSS->Get('txt_original'));

    //получаем массив подходящих под условие поиска id
    $ids = $eSS->makeSearch();
    $ids[] = 4294967295;
    if ($eSS->action == 'ids') {//работаем в режиме ids - сразу возвращаем ids
        $modx->setPlaceholder("evoSearchIDs", $ids);
        if ($eSS->params['output'] && $eSS->params['output'] == '1') {
            $output = implode(',', $ids);
        }
    } else {//работаем в полном режиме, возвращаем вместе с выводом результатов
        if (!empty($ids)) {
            $eSS->bulk_words_stemmer = array();
            foreach ($eSS->search_words as $v) {
                $eSS->bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
            }
            $DLparams = array(
                'documents' => implode(',', $ids),
                'sortType' => 'doclist',
                'showParent' => '1',
                'addWhereList' => 'c.searchable=1' . (isset($params['addWhereList']) ? ' AND ' . $params['addWhereList'] : '')
            );
            if (isset($params['parents'])) {
                $DLparams['addWhereList'] .= ' AND c.id IN (' . $DLparams['documents'] . ') ';
                unset($DLparams['documents']);
            }
            if ($eSS->params['extract'] == '1' && (!isset($params['prepare']) || $params['prepare'] == '')) {
                $DLparams['prepare'] = array($eSS, 'prepareExtractor');
            }
            $params = array_merge($params, $DLparams);
            $output .= $modx->runSnippet("DocLister", $params);
            if ($eSS->params['show_stat'] == '1') {
                $output = $eSS->getSearchResultInfo() . $output;
            }
        }
        $output = $output != '' ? $output : (!isset($_REQUEST[$eSS->search_field]) ? '' : '<div class="noResult">' . $eSS->parseNoresult($noResult) . '</div>');
    }
}
return $output;
