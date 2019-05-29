<?php
namespace exface\Core\Widgets\Parts\Charts\Traits;

use exface\Core\Widgets\Parts\Charts\ChartSeries;
use exface\Core\Widgets\Parts\Charts\Interfaces\StackableChartSeriesInterface;

trait StackableChartSeriesTrait
{
    /**
     *
     * @var bool
     */
    private $stacked = null;
    
    private $stack_group_id = null;
    
    /**
     * 
     * @return bool
     */
    public function isStacked() : bool
    {
        if ($this->stacked === null) {
            $index = $this->getIndex();
            if ($index > 0) {
                $prevSeries = $this->getChart()->getSeries()[($index-1)];
                if ($prevSeries instanceof StackableChartSeriesInterface && $prevSeries->isStacked() === true) {
                    $this->stacked = true;
                } else {
                    $this->stacked = false;
                }
            } else {
                $this->stacked = false;
            }
        }
        return $this->stacked;
    }
    
    /**
     * Set to true to stack all series of this chart
     *
     * @uxon-property stacked
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value
     * @return ChartSeries
     */
    public function setStacked(bool $value) : ChartSeries
    {
        $this->stacked = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */   
    public function getStackGroupId() : ?string
    {
        return $this->stack_group_id;
    }
    
    /**
     * @uxon-property stack_group_id
     * @uxon-type string
     * 
     * @param string $group
     * @return StackableChartSeriesTrait
     */
    public function setStackGroupId(string $group) : ChartSeries
    {
        $this->stacked = true;
        $this->stack_group_id = $group;
        return $this;
    }
}