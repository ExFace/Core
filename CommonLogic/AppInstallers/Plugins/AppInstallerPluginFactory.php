<?php
namespace exface\Core\CommonLogic\AppInstallers\Plugins;

use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\AbstractStaticFactory;
use exface\Core\Factories\FormulaFactory;
use exface\Core\Interfaces\Formulas\FormulaInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Selectors\FormulaSelector;
use exface\Core\CommonLogic\Model\SymfonyTokenStream;

/**
 * Factory for creating app installer plugin instances.
 */
abstract class AppInstallerPluginFactory extends AbstractStaticFactory
{
    public const PLUGIN_NAMESPACE = 'exface\\Core\\CommonLogic\\AppInstallers\\Plugins';

    /**
     * Create a new app installer plugin instance based on the specified expression.
     * 
     * If the given expression is not an app installer plugin, this call will identical to `FormulaFactory::createFromString()`.
     * 
     * @param WorkbenchInterface $workbench
     * @param string             $expression
     * @param string             $nameSpace
     * @return FormulaInterface
     * 
     * @see FormulaFactory::createFromString()
     */
    public static function createPlugin(
        WorkbenchInterface $workbench,
        string $expression,
        string $nameSpace = self::PLUGIN_NAMESPACE) : FormulaInterface
    {
        $tokenStream = new SymfonyTokenStream($expression);
        if(null ===  $functionName = $tokenStream->getFormulaName()) {
            throw new FormulaError("Can not create formula for expression {$tokenStream->__toString()}. No formula name found.");
        }
        
        $class = $nameSpace . '\\' . $functionName;
        $selector = new FormulaSelector($workbench, $functionName);
        
        if(class_exists($class)) {
            return new $class($selector, $tokenStream);
        } else {
            return FormulaFactory::createFromTokenStream($workbench, $tokenStream);
        }
    }
}