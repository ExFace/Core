<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\AggregatorFunctionsDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\MetaObjectFactory;

/**
 * Reads a value from the data source via attribute alias, object alias and filters with placeholders
 * 
 * Every argument of the formula supports placeholders. For example, to filter over a value from
 * the data sheet, that the formula is applied to, use `lookup_column == [#input_column#]` as
 * filter. Using placeholders makes the formula no-static of course.
 * 
 * ## Examples
 * 
 * - `=Lookup('ALIAS:LIST', 'exface.Core.APP')` - returns a comma-separated list of app aliases
 * - `=Lookup('UID', 'exface.Core.APP', 'ALIAS == exface.Core')` - returns the UID of the core app
 * - `=Lookup('ALIAS', 'exface.Core.APP', 'ALIAS == [#APP#]')` - returns the alias of the app with 
 * the UID found in column `APP` (e.g. of an attribute data sheet)
 * - `=Lookup('ALIAS:LIST', 'exface.Core.OBJECT', '{"operator": "AND", "conditions": [{"expression": "APP__ALIAS", "comparator": "==", "value": "exface.Core"}]}')` - 
 * returns a comma-separated list of object aliases in the Core app
 * 
 */
class Lookup extends Formula
{
    private $placeholders = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $expression = null, string $objectAlias = null, string $filters = null)
    {
        if ($expression === null || $expression === '' || $objectAlias === null || $objectAlias === '') {
            throw new FormulaError('Invalid argument values for formula "' . $this->__toString() . '": first and second argument cannot be empty!');
        }
        
        $phs = $this->getRequiredPlaceholders();
        if (! empty($phs)) {
            $row = $this->getDataSheet()->getRow($this->getCurrentRowNumber());
            $objectAlias = StringDataType::replacePlaceholders($objectAlias, $row);
            $expression = StringDataType::replacePlaceholders($expression, $row);
        }
        
        $lookupSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectAlias);
        $col = $lookupSheet->getColumns()->addFromExpression($expression);
        if ($filters !== null) {
            if (! empty($phs)) {
                $filters = StringDataType::replacePlaceholders($filters, $row);
            }
            $lookupSheet->setFilters(ConditionGroupFactory::createFromString($filters, $lookupSheet->getMetaObject()));
        }
        
        $lookupSheet->dataRead();
        
        if ($lookupSheet->countRows() > 1) {
            return $col->aggregate(($col->isAttribute() ? $col->getAttribute()->getDefaultAggregateFunction() : null) ?? AggregatorFunctionsDataType::LIST_ALL);
        }
        return $col->getCellValue(0);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getRequiredAttributes()
     */
    public function getRequiredAttributes(bool $withRelationPath = true) : array
    {
        return array_merge(parent::getRequiredAttributes(), $this->getRequiredPlaceholders());
    }
    
    /**
     *
     * @return string[]
     */
    protected function getRequiredPlaceholders() : array
    {
        if ($this->placeholders === null) {
            $phs = [];
            foreach ($this->getTokenStream()->getArguments() as $arg) {
                $phs = array_merge($phs, StringDataType::findPlaceholders($arg));
            }
            $this->placeholders = array_unique($phs);
        }
        return $this->placeholders;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::getDataType()
     */
    public function getDataType()
    {
        $attrAlias = $this->getTokenStream()->getArguments()[0];
        $objAlias = $this->getTokenStream()->getArguments()[1];
        $obj = MetaObjectFactory::createFromString($this->getWorkbench(), $objAlias);
        if ($obj->hasAttribute($attrAlias)) {
            return $obj->getAttribute($attrAlias)->getDataType();
        }
        return parent::getDataType();
    }
}