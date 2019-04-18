<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;

/**
 * Produces data connections (instances of data connectors configured for a specific connection)
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class DataConnectionFactory extends AbstractSelectableComponentFactory
{

    /**
     * Creates a data connector from the given selector and a UXON configuration
     * 
     * @param DataConnectorSelectorInterface $prototypeSelector
     * @param UxonObject $config
     * @return DataConnectionInterface
     */
    public static function create(
        DataConnectorSelectorInterface $prototypeSelector, 
        UxonObject $config, 
        string $uid = null,
        string $alias = null,
        string $namespace = null,
        string $name = null,
        bool $readonly = false) : DataConnectionInterface
    {
        $instance = static::createFromSelector($prototypeSelector);
        $instance->importUxonObject($config);
        $instance->setReadOnly($readonly);
        
        if ($uid !== null) {
            $instance->setId($uid);
        }
        
        if ($alias !== null) {
            $instance->setAlias($alias, $namespace);
        }
        
        if ($name !== null) {
            $instance->setName($name);
        }
        
        return $instance;
    }
    
    /**
     * Creates an instance of the connector specified by the given selector without any specific configuration.
     * 
     * @return DataConnectionInterface
     * 
     * @see \exface\Core\Factories\AbstractSelectableComponentFactory::createFromSelector()
     */
    public static function createFromSelector(SelectorInterface $prototypeSelector)
    {
        return parent::createFromSelector($prototypeSelector);
    }

    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $prototypeSelectorString
     * @param UxonObject $config
     * @return DataConnectionInterface
     */
    public static function createFromPrototype(WorkbenchInterface $workbench, string $prototypeSelectorString, UxonObject $config = null) : DataConnectionInterface
    {
        $selector = SelectorFactory::createDataConnectorSelector($workbench, $prototypeSelectorString);
        $instance =  static::createFromSelector($selector);
        if ($config !== null) {
            $instance->importUxonObject($config);
        }
        return $instance;
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $uidOrAlias
     * @return DataConnectionInterface
     */
    public static function createFromModel(WorkbenchInterface $workbench, string $uidOrAlias) : DataConnectionInterface
    {
        $selector = new DataConnectionSelector($workbench, $uidOrAlias);
        return $workbench->model()->getModelLoader()->loadDataConnection($selector);
    }
}