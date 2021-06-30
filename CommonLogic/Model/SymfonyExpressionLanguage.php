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

class SymfonyExpressionLanguage implements FormulaExpressionLanguageInterface, WorkbenchDependantInterface
{
    private $workbench = null;
    
    private $cacheName = '_expressions';
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;        
    }
    
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
        $name = $formula->getFormulaNameFromStream();
        
        //ExpressionLanguage does not support formulas with a namespace delimiter(e.g. 'exface.Core.AddDays()')
        //Therefore we have to replace the namespace delimiter with thesupported character '_'
        $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $name);
        $expression = str_replace($name, $fixedName, $expression);
        $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $formula);
        foreach ($formula->getNestedFormulas() as $funcName) {
            $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $funcName);
            $expression = str_replace($funcName, $fixedName, $expression);
            $nestedFormula = FormulaFactory::createFromSelector(new FormulaSelector($exface, $funcName));
            $nestedFormula->setTokenStream(new SymfonyTokenStream($funcName . '()'));
            $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $nestedFormula);
        }
        return $expressionLanguage->evaluate($expression, $row);
    }
    
    /**
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