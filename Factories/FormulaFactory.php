<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\FormulaSelector;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\CommonLogic\Model\SymfonyTokenStream;
use exface\Core\Exceptions\FormulaError;

abstract class FormulaFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a formula from the given name resolver and optionally specified array of arguments
     *
     * @param FormulaSelectorInterface $selector            
     * @param array $arguments            
     * @return Formula
     */
    public static function create(FormulaSelectorInterface $selector, array $arguments = array())
    {
        $formula = static::createFromSelector($selector);
        $formula->init($arguments);
        return $formula;
    }

    /**
     * Creates a Formula specified by the function name and an optional array of arguments.
     *
     * @param WorkbenchInterface $workbench            
     * @param string $function_name            
     * @param array $arguments            
     * @return Formula
     */
    public static function createFromString(WorkbenchInterface $workbench, string $expression)
    {
        $tokenStream = new SymfonyTokenStream($expression);
        $function_name = $tokenStream->getFormulaName();
        if ($function_name === null) {
            throw new FormulaError("Can not create formula for expression {$expression}. No formula name found.");
        }
        $selector = new FormulaSelector($workbench, $function_name);
        $formula = static::createFromSelector($selector);
        $formula->setTokenStream($tokenStream);
        return $formula;
    }
    
    public static function createFromTokenStream(WorkbenchInterface $workbench, FormulaTokenStreamInterface $stream)
    {
        $function_name = $stream->getFormulaName();
        if ($function_name === null) {
            throw new FormulaError("Can not create formula for expression {$stream->getExpression()}. No formula name found.");
        }
        $selector = new FormulaSelector($workbench, $function_name);
        $formula = static::createFromSelector($selector);
        $formula->setTokenStream($stream);
        return $formula;
    }
}
?>