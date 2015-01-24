<?php

//Класс для prepare-сниппета DocLister (готовим данные для плейсхолдера [+extract+] в чанк вывода результатов DocLister
class evoSearchDLPrepare{
	//делаем подсветку на основе стеммера
	public static function makeHighlight ($data, $modx, $_DL, $_extDL) {
	    $bulk_words_stemmer = $_DL->getCFGDef('bulk_words_stemmer', array());

	    if (is_array($bulk_words_stemmer) && !empty($bulk_words_stemmer)) {
	        $input = implode('|', $bulk_words_stemmer);
	        $input = str_replace('\\', '', $input);
	        $pattern = '/(' . $input . ')([^\.\s\;\:"\'\(\)!?,]*)?/ius';
	        $replacement = '<span class="evoSearch_highlight">$1$2</span>';
	        if ($_DL->getCFGDef('extract_with_tv', 0) == '1') {
	            $ext_content_field = $_DL->getCFGDef('ext_content_field');
	            $text = self::getTextForHighlight($data[$ext_content_field], $modx, $_DL);
	        } else{
	            $text = self::getTextForHighlight($data["content"], $modx, $_DL);
	        }
	        $pagetitle = $modx->stripTags($data["pagetitle"]);
	        $data["extract"] = preg_replace($pattern, $replacement, $text);
	        $data["pagetitle"] = preg_replace($pattern, $replacement, $pagetitle);
	    }
	    return $data;
	}

	//вырезаем нужный кусок текста нужной длины (примерно)
	protected static function getTextForHighlight($text, $modx, $_DL) {
	    $maxlength = $_DL->getCFGDef('maxlength', 350);
	    $max_length = (int)$maxlength != 0 ? (int)$maxlength : 350;
	    $limit = $max_length + 12;
	    $text = $modx->stripTags($text);
	    $pos = array();
	    $bulk_words_stemmer = $_DL->getCFGDef('bulk_words_stemmer', array());
	    foreach ($bulk_words_stemmer as $word) {
	        $pos[$word] = mb_strripos(mb_strtolower($text, 'UTF-8'), $word, 0, 'UTF-8');
	    }
	    foreach ($pos as $word => $position) {
	        $length = mb_strlen($text, 'UTF-8');
	        if ($position == 0 && $length > $limit) {
	            $text = mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
	        } else if ($position < $max_length && $length > $limit) {
	            $text = ' ... ' . mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
	        } else if ($position + $limit >= $length && $length > $limit) {
	            $text = mb_substr($text, $position);
	        } else if ($length > $limit){
	            $text = ' ... ' . mb_substr($text, $position, $max_length, 'UTF-8') . ' ... ';
	        } else {

	        }
	    }
	    return $text;
	}
}