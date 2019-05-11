<?php
namespace exface\Core\Widgets\Parts\Charts;

class ColumnChart extends LineChart
{
    
    /**
     *
     * @var boolean
     */
    private $stack = false;
    
    public function isStacked() : bool
    {
        return $this->stack;
    }
    
    /**
     * Set to true to stack all series of this chart
     *
     * @uxon-property stack_series
     *
     * @param boolean $value
     * @return ColumnChart
     */
    public function setStacked(bool $value) : ColumnChart
    {
        $this->stack = $value;
        return $this;
    }
}