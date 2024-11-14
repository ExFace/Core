<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Returns the value formatted by the datatype either given with namespace or determined automatically from
 * the attribute.
 * The datatype used is determined from the first found attribute in the formula, as the formula only supports
 * one attribute that is ok.
 * 
 * E.g. 
 * - `=Format(MODIFIED_ON)` => 24.07.2022 12:00:00
 * - `=Format('2008-12-07', 'exface.core.Date')` => 07.12.2008
 * 
 **/
class Format extends Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see Formula::run()
     */
    public function run($value = null, $dataTypeAlias = null)
    {
        if ($value === null || $value === '') {
            return '';
        }
        
        return $this->getInputDataType($dataTypeAlias)->format($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see Formula::getDataType()
     */
    public function getDataType() : DataTypeInterface
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
}