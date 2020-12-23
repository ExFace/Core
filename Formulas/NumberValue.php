<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

class NumberValue extends \exface\Core\CommonLogic\Model\Formula
{

    function run($string)
    {
        try {
            $number = NumberDataType::cast($string);
        } catch (\exface\Core\Exceptions\DataTypes\DataTypeCastingError $e) {
            return '';
        }
        return $number;
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