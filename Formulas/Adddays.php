<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\DateTimeDataType;

class Adddays extends Formula
{
    /**
     * 
     * @param string $date
     * @param int $days_to_add
     * @return string
     */
    function run($date, int $days_to_add = null)
    {
        if (! $date) {
            return;
        }
        $date = new Date(DateTimeDataType::cast($date));
        $interval = ($days_to_add < 0 ? 'N' : 'P') . intval($days_to_add) . 'D';
        $date->add(new \DateInterval($interval));
                
        return DateTimeDataType::formatDateNormalized($date);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), DateTimeDataType::class);
    }
}
?>