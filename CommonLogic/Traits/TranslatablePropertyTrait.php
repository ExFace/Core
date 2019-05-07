<?php
namespace exface\Core\CommonLogic\Traits;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\RuntimeException;

/**
 * This trait contains a method to evaluate a string property, that accepts scalars and
 * static formulas like =Translate(...).
 * 
 * This is particularly usefull for translatable UXON properties: just call
 * `$this->evaluatePropertyExpression($propertyValue)` in the getter or setter
 * method of the property: 
 * - if the property contains a formula, it will be evaluated,
 * - if not, the property value will be returned as-is.
 * 
 * @author Andrej Kabachnik
 *
 */
trait TranslatablePropertyTrait {
    
    /**
     * Evaluates the given $propertyValue if it is a static expression or returns it
     * unmodified if it is a scalar expression (e.g. string or number).
     *
     * This can be used to translate certain attributes, e.g. the caption:
     * =TRANSLATE('exface.Core', 'TRANSLATION.KEY', '%placeholder1%=>value1|%placeholder2%=>value2', '1')
     * =TRANSLATE('exface.Core', 'ACTION.CREATEDATA.RESULT', '%number%=>Zwei', '2')
     * =TRANSLATE('exface.Core', 'ACTION.CREATEDATA.NAME')
     *
     * @param string $string
     * @throws RuntimeException if a non-static formula is used
     * @return string
     */
    protected function evaluatePropertyExpression(string $propertyValue) : string
    {
        if ($this->isValueFormula($propertyValue) === true) {
            $expr = ExpressionFactory::createFromString($this->getWorkbench(), $propertyValue);
            if ($expr->isStatic()) {
                return $expr->evaluate();
            } else {
                throw new RuntimeException('Cannot use dynamic formula "' . $propertyValue . '" as a static value!');
            }
        }
        return $propertyValue;
    }
    
    /**
     * Returns TRUE if the passed $value is a formula.
     * 
     * @param string $value
     * @return bool
     */
    protected function isValueFormula(string $value) : bool
    {
        return Expression::detectFormula($value) === true;
    }
}
