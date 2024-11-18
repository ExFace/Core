<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;

/**
 * Returns the value formatted by the datatype either given with namespace or determined automatically from the attribute.
 * 
 * The datatype used is determined from the first found attribute in the formula, as the formula only supports
 * one attribute that is ok.
 * 
 * E.g. 
 * 
 * - `=Format(MODIFIED_ON)` => 24.07.2022 12:00:00
 * - `=Format(MODIFIED_ON, 'exface.core.Date')` => 07.12.2008
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

        if ($dataTypeAlias) {
            $dataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeAlias);
        } else {
            $dataType = $this->getArgumentType(0);
        }

        if ($dataType === null) {
            throw new FormulaError('Formula does not contain any attribute to determine datatype from to format value!');
        }

        return $dataType->format($value);
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