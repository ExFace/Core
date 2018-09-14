<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class DataConnectorFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a data connector from the given selector and an optional config array
     * 
     * @param DataConnectorSelectorInterface $selector
     * @param UxonObject $config
     * @return DataConnectionInterface
     */
    public static function create(DataConnectorSelectorInterface $selector, UxonObject $config = null) : DataConnectionInterface
    {
        $instance = static::createFromSelector($selector);
        if ($config !== null) {
            $instance->importUxonObject($config);
        }
        return $instance;
    }

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $selectorString
     * @param UxonObject $config
     * @return \exface\Core\Interfaces\DataSources\DataConnectionInterface
     */
    public static function createFromAlias(WorkbenchInterface $workbench, string $selectorString, UxonObject $config = null) : DataConnectionInterface
    {
        $selector = SelectorFactory::createDataConnectorSelector($workbench, $selectorString);
        return static::create($selector, $config);
    }
}
?>