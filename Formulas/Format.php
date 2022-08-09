<?php
namespace exface\Core\Formulas;

use exface\Core\DataTypes\NumberDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Exceptions\DataTypes\DataTypeCastingError;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\FormulaError;

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
class Format extends \exface\Core\CommonLogic\Model\Formula
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run($value = null, $dataTypeAlias = null)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($dataTypeAlias) {
            $dataType = DataTypeFactory::createFromString($this->getWorkbench(), $dataTypeAlias);
        } else {
            if (! $this->getTokenStream()->getAttributes()[0]) {
                throw new FormulaError('Formula does not contain any attribute to determine datatype from to format value!');
            }
            $ds = $this->getDataSheet();
            if (! $ds) {
                throw new FormulaError('Formula can not be evaluated stically if no datatype is explicitly given!');
            }
            $attr = $ds->getMetaObject()->getAttribute($this->getTokenStream()->getAttributes()[0]);
            $dataType = $attr->getDataType();
        }
        
        return $dataType->format($value);
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        return DataTypeFactory::createFromPrototype($this->getWorkbench(), StringDataType::class);
    }
}