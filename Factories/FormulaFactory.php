<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\FormulaSelector;
use exface\Core\Interfaces\Formulas\FormulaTokenStreamInterface;
use exface\Core\CommonLogic\Model\SymfonyTokenStream;
use exface\Core\Exceptions\FormulaError;
use exface\Core\CommonLogic\Model\EmptyTokenStream;

abstract class FormulaFactory extends AbstractSelectableComponentFactory
{

    private static $cache = [];
    
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
        return static::createFromTokenStream($workbench, $tokenStream);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param FormulaTokenStreamInterface $tokenStream
     * @throws FormulaError
     * @return mixed
     */
    public static function createFromTokenStream(WorkbenchInterface $workbench, FormulaTokenStreamInterface $tokenStream)
    {
        $function_name = $tokenStream->getFormulaName();
        if ($function_name === null) {
            throw new FormulaError("Can not create formula for expression {$tokenStream->__toString()}. No formula name found.");
        }
        $selector = new FormulaSelector($workbench, $function_name);
        if ($tokenStream instanceof EmptyTokenStream) {
            return static::createFromSelector($selector, [$selector, $tokenStream]);
        }
        $str = $tokenStream->__toString();
        if (! isset(self::$cache[$str])) {
            self::$cache[$str] = $tokenStream;
        }
        $formula = static::createFromSelector($selector, [$selector, self::$cache[$str]]);
        return $formula;
    }
}
?>