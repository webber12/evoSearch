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
    $q = $modx->db->query($sql);
    $content_fields = explode(',', $eSP->cleanIn($eSP->search_fields));
    while ($row = $modx->db->getRow($q)) {
        $tmp = array();
        foreach($content_fields as $k => $v) {
            if (isset($row[$v]) && !empty($row[$v])) {
                $tmp[] = $row[$v];
            }
        }
        $content_original = implode(' ', $tmp);
        $content = $modx->stripTags($content_original);
        $content = $eSP->injectTVs($row['id'], $content);
        $words = $eSP->Words2BaseForm(mb_strtoupper($content, 'UTF-8'));
        $fields = array(
            $eSP->ext_content_field => $modx->db->escape($content),
            $eSP->ext_content_index_field => $modx->db->escape($words),
            'docid' => $row['id'],
            'pagetitle' => isset($row['pagetitle']) ? $modx->db->escape($row['pagetitle']) : '',
            'table' => $eSP->content_table_name
        );
        $up = $eSP->updateSearchTable($fields);
        //die();
    }
    //удалим строки индексов для исключенных шаблонов и ресурсов
    $eSP->emptyExcluded();
}
