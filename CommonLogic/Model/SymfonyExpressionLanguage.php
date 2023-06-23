<?php

namespace exface\Core\CommonLogic\Model;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use exface\Core\Interfaces\Formulas\FormulaExpressionLanguageInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Factories\FormulaFactory;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Wraper class to evaluate a formula expression using Symfony/ExpressionLanguage
 * 
 * @author ralf.mulansky
 *
 */
class SymfonyExpressionLanguage implements FormulaExpressionLanguageInterface, WorkbenchDependantInterface
{
    private $workbench = null;
    
    private $cacheName = '_expressions';
    
    private $dataSheet = null;
    
    private $dataSheetRowIdx = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param DataSheetInterface $dataSheet
     * @param int $rowIdx
     */
    public function __construct(WorkbenchInterface $workbench, DataSheetInterface $dataSheet = null, int $rowIdx = null)
    {
        $this->workbench = $workbench;    
        $this->dataSheet = $dataSheet;
        $this->dataSheetRowIdx = $rowIdx;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Formulas\FormulaExpressionLanguageInterface::evaluate()
     */
    public function evaluate(FormulaInterface $formula, array $row)
    {
        $exface = $this->getWorkbench();
        
        if ($exface->getCache()->hasPool($this->cacheName)) {
            $cache = $exface->getCache()->getPool($this->cacheName, false);    
        } else {            
            $cache = $exface->getCache()->createDefaultPool($exface, $this->cacheName, false);
            $exface->getCache()->addPool($this->cacheName, $cache);
        }
        
        $formula->setDataContext($this->dataSheet, $this->dataSheetRowIdx);
        
        $expressionLanguage = new ExpressionLanguage($cache);
        $expression = $formula->__toString();
        $name = $formula->getFormulaName();
        
        // Add callbacks for nested formulas (just those, that really are used!)
        // ExpressionLanguage does not support formulas with a namespace delimiter(e.g. 'exface.Core.AddDays()')
        // Therefore we have to replace the namespace delimiter with thesupported character '_'
        $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $name);
        $expression = str_replace($name, $fixedName, $expression);
        $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $formula);
        foreach ($formula->getNestedFormulas() as $funcName) {
            $fixedName = str_replace(AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER, '_', $funcName);
            $expression = str_replace($funcName, $fixedName, $expression);
            // Use dummy classes as nested formulas (without a token stream as all tokens were already
            // evaluated in this formula - this saves us lots of parsing!
            $nestedFormula = FormulaFactory::createFromTokenStream($exface, new EmptyTokenStream($funcName));
            $this->addFunctionToExpressionLanguage($expressionLanguage, $fixedName, $nestedFormula);
        }
        
        // Add the columns of the current data sheet row as variable arguments
        // Note, that if the formula has a relation path, the tokens will not contain it! So
        // we need to replace attribute aliases _without_ the formulas relation path with 
        // column names _with_ relation path!
        // For example, if we have `Concatenate(lat, ',', lng)` (a formula to get the latlng coordinates 
        // from both values separately) used with the relation path `location`, than `lat` needs
        // to be replaced with `location__lat` and `lng` with `location__lng` to match the column
        // names in the provided data row.
        
        //TODO parse the values before evaluating the column?
        $attrsArgs = $formula->getRequiredAttributes(false);
        $attrsRequired = $formula->getRequiredAttributes(true);
        foreach ($attrsRequired as $i => $attrAlias) {
            $columnName = DataColumn::sanitizeColumnName($attrAlias);
            $expression = str_replace($attrsArgs[$i], $columnName, $expression);
        }
        $value = $expressionLanguage->evaluate($expression, $row);
        
        $formula->setDataContext(null);
        
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
            $formula->setDataContext($this->dataSheet, $this->dataSheetRowIdx);
            $result = call_user_func_array([
                $formula,
                'run'
            ], $args);
            $formula->setDataContext(null);
            return $result;
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