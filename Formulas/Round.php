<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;

class Round extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($number = null, $digits = 0, $pad_digits = false)
    {
        if ($number === null || $number === '') {
            return null;
        }
        
        try {
            $number = NumberDataType::cast($number);
        } catch (DataTypeCastingError $e) {
            return $number;
        }
        
        $rounded_number = round($number, $digits);
        if ($pad_digits) {
            $rounded_number = number_format($rounded_number, $digits, '.', '');
        }
        return $rounded_number;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), NumberDataType::class);
    }
}