<?php
/**
 * @var object $modx
 */
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

if ($modx->event->name !== 'OnDocFormSave') {
    return;
}

include_once('plugin.class.php');
$eSP = new evoSearchPlugin($modx, $modx->event->params);
//проверяем наличие и создаем при необходимости нужные поля и индекса
$eSP->prepareRun();
//теперь начинаем работу
$sql = $eSP->makeSQLForSelectWords();
//echo $sql;die();
$q = $modx->db->query($sql);
$content_fields = explode(',', $eSP->cleanIn($eSP->search_fields));
while ($row = $modx->db->getRow($q)) {
    $tmp = [];
    foreach ($content_fields as $k => $v) {
        if (isset($row[$v]) && !empty($row[$v])) {
            $tmp[] = $row[$v];
        }
    }
    $content_original = implode(' ', $tmp);
    $content = $modx->stripTags($content_original);
    $content = $eSP->injectTVs($row['id'], $content);
    $words = $eSP->Words2BaseForm(mb_strtoupper($content, 'UTF-8'));
    $up = $eSP->updateSearchTable([
        $eSP->ext_content_field => $modx->db->escape($content),
        $eSP->ext_content_index_field => $modx->db->escape($words),
        'docid' => $row['id'],
        'pagetitle' => !isset($row['pagetitle'])
            ? ''
            : $modx->db->escape($row['pagetitle']),
        'table' => $eSP->content_table_name
    ]);
}
//удалим строки индексов для исключенных шаблонов и ресурсов
$eSP->emptyExcluded();
