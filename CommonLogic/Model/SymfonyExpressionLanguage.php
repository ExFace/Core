<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use exface\Core\Interfaces\Formulas\FormulaExpressionLanguageInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\FormulaFactory;
use exface\Core\CommonLogic\Selectors\FormulaSelector;
use exface\Core\Exceptions\OutOfBoundsException;
use exface\Core\CommonLogic\DataSheets\DataColumn;

class SymfonyExpressionLanguage implements FormulaExpressionLanguageInterface, WorkbenchDependantInterface
{
    private $workbench = null;
    
    private $cacheName = '_expressions';
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;        
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaExpressionLanguageInterface::evaluate()
     */
    public function evaluate(FormulaInterface $formula, array $row)
    {
        $exface = $this->getWorkbench();
        try {
            $cache = $exface->getCache()->getPool($this->cacheName, false);
        } catch (OutOfBoundsException $e) {
            $cache = $exface->getCache()->createDefaultPool($exface, $this->cacheName, false);
        }
        $expressionLanguage = new ExpressionLanguage($cache);
        $expression = $formula->getExpression();
        $name = $formula->getFormulaName();
        
        //ExpressionLanguage does not support formulas with a namespace delimiter(e.g. 'exface.Core.AddDays()')
        //Therefore we have to replace the namespace delimiter with thesupported character '_'
        $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $name);
        $expression = str_replace($name, $fixedName, $expression);
        $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $formula);
        foreach ($formula->getNestedFormulas() as $funcName) {
            $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $funcName);
            $expression = str_replace($funcName, $fixedName, $expression);
            $nestedFormula = FormulaFactory::createFromTokenStream($exface, new EmptyTokenStream($funcName));
            $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $nestedFormula);
        }
        foreach ($formula->getRequiredAttributes() as $attrAlias) {
            $columnName = DataColumn::sanitizeColumnName($attrAlias);
            $expression = str_replace($attrAlias, $columnName, $expression);
        }
        $value = $expressionLanguage->evaluate($expression, $row);
        return $value;
    }
    
    /**
     * Add a given `formula` with the given `funcName` to the given `ExpressionLanguage` instance
     *
     * @param ExpressionLanguage $expressionLanguage
     * @param string $funcName
     * @param FormulaInterface $formula
     * @return Formula
     */
    protected function addFunctionToExpressionLanguage(ExpressionLanguage $expressionLanguage, string $funcName, FormulaInterface $formula) : Formula
    {
        $expressionLanguage->register($funcName, function ($str) {}, function() use ($formula) {
            //get all arguments given to the function
            $args = func_get_args();
            //cut of the first one as it is the array given to the evalute function call as second argument
            array_shift($args);
            return call_user_func_array([
                $formula,
                'run'
            ], $args);
        });
            return $formula;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

}