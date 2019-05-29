<?php
namespace exface\Core\Widgets\Parts\Charts;

class RoseChartSeries extends PieChartSeries
{    
    private $value_mode = null;
    
    /**
     * Type of the Nightingale Diagramm. Choose 'angle' or 'radius'.
     * 'radius' - data value will be distinguished by the radius.
     * 'angle'- ata value will be distinguished by the angle.
     *
     * @uxon-property value_mode
     * @uxon-type string [ radius, angle ]
     *
     * @param string $roseType
     * @return PieChartSeries
     */
    public function setValueMode(string $roseType) : RoseChartSeries
    {
        $this->value_mode = $roseType;
        return $this;
    }
    
    /**
     *
     * @return string|NULL
     */
    public function getValueMode() : ?string
    {
        return $this->value_mode;
    }
}