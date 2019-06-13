<?php
namespace exface\Core\Widgets\Parts\Charts;

class DonutChartSeries extends PieChartSeries
{       
    /**
     *
     * @return string
     */
    public function getInnerRadius() : string
    {
        if (parent::getInnerRadius() =='0%'){
            return '40%';
        }
        return parent::getInnerRadius();
    }     
}