<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\DataTypes\DateDataType;
use exface\Core\Factories\DataTypeFactory;

class Adddays extends Formula
{

    function run($date, $days_to_add = null)
    {
        if (! $date)
            return;
        
        $date = new \DateTime($date);
        $interval = ($days_to_add < 0 ? 'N' : 'P') . intval($days_to_add) . 'D';
        $date->add(new \DateInterval($interval));
        $dataType = DataTypeFactory::createFromPrototype($this->getWorkbench(), DateDataType::class);
                
        return $dataType->formatDate($date);
    }
}
?>