<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Formula;
use exface\Core\Interfaces\Selectors\FormulaSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\FormulaSelector;

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
    public static function createFromString(WorkbenchInterface $workbench, $function_name, array $arguments = array())
    {
        $selector = new FormulaSelector($workbench, $function_name);
        return static::create($selector, $arguments);
    }
}
?>