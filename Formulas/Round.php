<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

class Round extends \exface\Core\CommonLogic\Model\Formula
{

    function run($number, $digits = 0, $pad_digits = false)
    {
        if (is_numeric($number)) {
            $rounded_number = round($number, $digits);
            if ($pad_digits) {
                $rounded_number = number_format($rounded_number, $digits, '.', '');
            }
            return $rounded_number;
        } else {
            return $number;
        }
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