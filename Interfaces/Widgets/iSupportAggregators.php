<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;

interface iSupportAggregators extends WidgetInterface
{
    /**
     * 
     * @return AggregatorInterface
     */
    public function getAggregator();
    
    /**
     *
     * @param string|AggregatorInterface $aggregator_or_string
     * @return iSupportAggregators
     */
    public function setAggregator($aggregator_or_string);
}