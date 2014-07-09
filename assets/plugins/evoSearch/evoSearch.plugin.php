<?php
if(!defined('MODX_BASE_PATH')) die('What are you doing? Get out of here!');

$e = $modx->Event;
if ($e->name == 'OnDocFormSave') {
    include_once('plugin.class.php');
    $eSP = new evoSearchPlugin($modx, $e->params);
    $sql = $eSP->makeSQLForSelectWords();
    //echo $sql;die();
    $q = $eSP->modx->db->query($sql);
    while ($row = $eSP->modx->db->getRow($q)) {
        $content_original = $row['pagetitle'] . ' ' . $row['longtitle'] . ' ' . $row['description'] . ' ' . $row['introtext'] . ' ' . $row['content'];
        $content = $eSP->modx->stripTags($content_original);
        $content = $eSP->injectTVs($row['id'], $content);
        $words = $eSP->Words2BaseForm($content);
        $upd = $eSP->modx->db->update(
            array($eSP->ext_content_field => $eSP->modx->db->escape($content), $eSP->ext_content_index_field => $eSP->modx->db->escape($words)),
            $eSP->content_table,
            'id=' . $row['id']
            );
        //echo $words;
        //echo '<hr>';
        //die();
    }
}
