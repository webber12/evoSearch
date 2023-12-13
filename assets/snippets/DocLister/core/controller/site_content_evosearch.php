<?php
/**
 * site_content_evosearch for evoSearch snippet
 */

include_once(dirname(__FILE__) . "/site_content.php");


class site_content_evosearchDocLister extends site_contentDocLister
{
    public function __construct($modx, $cfg = array(), $startTime = null)
    {
        parent::__construct($modx, $cfg, $startTime);
        $this->joinSearchTable();
    }

    protected function joinSearchTable()
    {
        $this->_filters['join'] = ' LEFT JOIN ' . $this->getTable('evosearch_table', 'es') . ' on es.docid=c.id';
        $selectFields = $this->getCFGDef('selectFields', 'c.*');
        $selectFields .= ', es.content_with_tv';
        $this->config->setConfig([ 'selectFields' => $selectFields ]);
        return $this;
    }
}
