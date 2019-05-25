<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\TimestampDataType;
use exface\Core\Factories\DataTypeFactory;

class Timestamp extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     *
     * @param string $date            
     * @param number $multiplier            
     * @return number
     */
    function run($date, $multiplier = 1000)
    {
        if (! $date)
            return 0;
        return strtotime($date) * $multiplier;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), TimestampDataType::class);
    }
}