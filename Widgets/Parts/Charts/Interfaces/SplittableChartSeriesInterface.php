<?php
namespace exface\Core\Widgets\Parts\Charts\Interfaces;

use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\DataColumn;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

interface SplittableChartSeriesInterface
{    
    
    
    /**
     * 
     * @param string $value
     * @return ChartSeries
     */
    public function setSplitByAttributeAlias(string $value) : ChartSeries;
    
    /**
     *
     * @return string|NULL
     */
    public function getSplitByAttributeAlias() : ?string;
    
    /**
     *
     * @return bool
     */
    public function isSplitByAttribute() : bool;
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getSplitByAttribute() : MetaAttributeInterface;
    
    /**
     * 
     * @return DataColumn
     */
    public function getSplitByDataColumn() : DataColumn;
}