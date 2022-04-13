<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\DataTypes\AggregatorFunctionsDataType;

/**
 * Reads a value from the data source
 * 
 * Examples
 * 
 * - `=Lookup('ALIAS:LIST', 'exface.Core.APP')` - returns a comma-separated list of app aliases
 * - `=Lookup('ALIAS:LIST', 'exface.Core.OBJECT', '{"operator": "AND", conditions[{"expression": "APP__ALIAS", "comparator": "==", "value": "exface.Core"}]}')` - 
 * returns a comma-separated list of object aliases in the Core app
 * 
 */
class Lookup extends Formula
{
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
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $objectAlias);
        $col = $ds->getColumns()->addFromExpression($expression);
        if ($filters !== null) {
            $ds->setFilters(ConditionGroupFactory::createFromString($filters, $ds->getMetaObject()));
        }
        $ds->dataRead();
        if ($ds->countRows() > 1) {
            return $col->aggregate(($col->isAttribute() ? $col->getAttribute()->getDefaultAggregateFunction() : null) ?? AggregatorFunctionsDataType::LIST_ALL);
        }
        return $col->getCellValue(0);
    }
}