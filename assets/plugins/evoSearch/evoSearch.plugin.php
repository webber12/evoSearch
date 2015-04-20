<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$e = $modx->Event;
if ($e->name == 'OnDocFormSave') {
    include_once('plugin.class.php');
    $eSP = new evoSearchPlugin($modx, $e->params);
    //проверяем наличие и создаем при необходимости нужные поля и индекса
    $eSP->prepareRun();
    //теперь начинаем работу
    $sql = $eSP->makeSQLForSelectWords();
    //echo $sql;die();
    $q = $eSP->modx->db->query($sql);
    $where = '';
    $where .= $eSP->params['excludeTmpls'] != '' ? ' template NOT IN(' . $eSP->params['excludeTmpls'] . ') ' : '';
    $where .= $eSP->params['excludeIDs'] != '' ? ($where != '' ? ' OR ' : '') . ' id NOT IN(' . $eSP->params['excludeIDs'] . ') ' : '';
    $where = !empty($where) ? ' AND (' . $where . ')' : ''; 
    while ($row = $eSP->modx->db->getRow($q)) {
        $content_original = $row['pagetitle'] . ' ' . $row['longtitle'] . ' ' . $row['description'] . ' ' . $row['introtext'] . ' ' . $row['content'];
        $content = $eSP->modx->stripTags($content_original);
        $content = $eSP->injectTVs($row['id'], $content);
        $words = $eSP->Words2BaseForm(mb_strtoupper($content, 'UTF-8'));
        $upd = $eSP->modx->db->update(
            array($eSP->ext_content_field => $eSP->modx->db->escape($content), $eSP->ext_content_index_field => $eSP->modx->db->escape($words)),
            $eSP->content_table,
            'id=' . $row['id'] . $where
            );
        //echo $words;
        //echo '<hr>';
        //die();
    }
    //сбросим в пустоту поля индексов для исключенных шаблонов и ресурсов
    $eSP->emptyExcluded();
}
