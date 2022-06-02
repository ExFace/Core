<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;

/**
 * Attempts to convert a given value to a number optionally formatting it.
 * 
 * The first parameter is the value to parse, while the second (optional) parameter is
 * the format: `system`, `locale`.
 * 
 * Examples:
 * 
 * - `=NumberValue('1 500,22')` = `1500.22`
 * - `=NumberValue('1.500,22')` = `1500.22`
 * - `=NumberValue('1500.22')` = `1500.22`
 * - `=NumberValue('1500.22', 'locale')` = `1500,22`
 * 
 * @author Andrej Kabachnik
 *
 */
class NumberValue extends \exface\Core\CommonLogic\Model\Formula
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($string = null, $returnFormat = null, $ifNull = '')
    {
        if ($string === null || $string === '' || $string === EXF_LOGICAL_NULL) {
            return $ifNull;
        }
        try {
            $number = NumberDataType::cast($string);
        } catch (\exface\Core\Exceptions\DataTypes\DataTypeCastingError $e) {
            return null;
        }
        
        switch ($returnFormat) {
            case 'system':
            case null:
                break;
            case 'locale':
                $number = $this->getDataType()->format($number, null, $ifNull);
                break;
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