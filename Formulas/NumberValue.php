<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

class NumberValue extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($string)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        try {
            $number = NumberDataType::cast($string);
        } catch (\exface\Core\Exceptions\DataTypes\DataTypeCastingError $e) {
            return null;
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