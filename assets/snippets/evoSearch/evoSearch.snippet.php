<?php
/**
 * @var object $modx
 */

if(!defined('MODX_BASE_PATH')) {
    die('What are you doing? Get out of here!');
}

$txt_original = '';

include_once('snippet.class.php');
if (!isset($params['minlength']) || (int)$params['minlength'] <= 0) {
    $min_length = 2;
} else {
    $min_length = (int)$params['minlength'];
}
$eSS = new evoSearchSnippet($modx, $params, $min_length);

if (!isset($_GET[$eSS->search_field]) || $_GET[$eSS->search_field] == '') {
    return null;
}

$eSS->init();
$eSS->prepareWords();
$modx->setPlaceholder('stat_request', $eSS->Get('txt_original'));

//получаем массив подходящих под условие поиска id
$ids = $eSS->makeSearch();
$ids[] = 4294967295;

if ($eSS->action === 'ids') {//работаем в режиме ids - сразу возвращаем ids
    $modx->setPlaceholder("evoSearchIDs", $ids);
    if (!$eSS->params['output'] || $eSS->params['output'] != 1) {
        return null;
    }
    return implode(',', $ids);
}

if (empty($ids)) {
    if (!isset($_REQUEST[$eSS->search_field])) {
        return null;
    }
    if (!isset($params['noResult'])) {
        $noResult = 'По запросу <u>[+stat_request+]</u> ничего не найдено. Смягчите условия поиска';
    } else {
        $noResult = $params['noResult'];
    }
    return '<div class="noResult">' . $eSS->parseNoresult($noResult) . '</div>';
}

//работаем в полном режиме, возвращаем вместе с выводом результатов
$eSS->bulk_words_stemmer = [];
foreach ($eSS->search_words as $v) {
    $eSS->bulk_words_stemmer[] = $eSS->stemmer->stem_word($v);
}
$DLparams = [
    'documents' => implode(',', $ids),
    'sortType' => 'doclist',
    'showParent' => '1',
    'addWhereList' => 'c.searchable=1'
        . (isset($params['addWhereList'])
            ? ' AND ' . $params['addWhereList']
            : ''
        )
];
if (isset($params['parents'])) {
    $DLparams['addWhereList'] .= ' AND c.id IN (' . $DLparams['documents'] . ') ';
    unset($DLparams['documents']);
}
if ($eSS->params['extract'] == '1' && (!isset($params['prepare']) || $params['prepare'] == '')) {
    $DLparams['prepare'] = [$eSS, 'prepareExtractor'];
}

$params = array_merge($params, $DLparams);
$output = $modx->runSnippet('DocLister', $params);
if ($eSS->params['show_stat'] == 1) {
    return $eSS->getSearchResultInfo() . $output;
}

if ($output == '') {
    if (!isset($_REQUEST[$eSS->search_field])) {
        return null;
    }
    return '<div class="noResult">' . $eSS->parseNoresult($noResult) . '</div>';
}

return $output;
