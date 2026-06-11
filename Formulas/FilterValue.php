<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Exceptions\FormulaError;

/**
 * Returns the value of the first filter condition for the given attribute from the current data sheet.
 *
 * The formula inspects all filter conditions of the data sheet including nested groups and returns
 * the first matching value. If no matching condition is found, `null` is returned.
 *
 * ## Examples
 *
 * - `=FilterValue('STATUS')` - returns the first filter value for attribute `STATUS`
 * - `=FilterValue('STATUS', '==')` - returns the first filter value for `STATUS` with comparator `==`
 */
class FilterValue extends Formula
{
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::run()
     */
    public function run(string $attributeAlias = null, string $comparator = null)
    {
        if ($attributeAlias === null || $attributeAlias === '') {
            throw new FormulaError($this, 'Invalid argument values for formula "' . $this->__toString() . '": first argument cannot be empty!');
        }

        $dataSheet = $this->getDataSheet();
        if ($dataSheet === null) {
            throw new FormulaError($this, 'Cannot evaluate formula "' . $this->__toString() . '": no data sheet context available!');
        }

        foreach ($dataSheet->getFilters()->getConditionsRecursive() as $condition) {
            if ($condition->getAttributeAlias() !== $attributeAlias) {
                continue;
            }
            if ($comparator !== null && $comparator !== '' && $condition->getComparator() !== $comparator) {
                continue;
            }
            return $condition->getValue();
        }

        return null;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Formula::isStatic()
     */
    public function isStatic() : bool
    {
        return false;
    }
}
