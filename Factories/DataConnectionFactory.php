<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\DataConnectors\ModelLoaderConnector;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;

/**
 * Produces data connections (instances of data connectors configured for a specific connection)
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class DataConnectionFactory extends AbstractSelectableComponentFactory
{
    const METAMODEL_CONNECTION_ALIAS = 'exface.Core.METAMODEL_CONNECTION';
    
    const METAMODEL_CONNECTION_UID = '0x11ea72c00f0fadeca3480205857feb80';
    
    /**
     * Creates ready-to-use data connection from the given connector selector and a UXON configuration
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
     * Creates a connection from a connection selector or a connector prototype selector.
     * 
     * If it's a connection, it's configuration will be loaded from the metamodel. If it's
     * a prototype, an empty (not configured) connection will result.
     * 
     * @return DataConnectionInterface|DataConnectorSelectorInterface
     * 
     * @see \exface\Core\Factories\AbstractSelectableComponentFactory::createFromSelector()
     */
    public static function createFromSelector(SelectorInterface $connectorSelector) : DataConnectionInterface
    {
        // If it's a selector for a configured connection, load it from the model
        if ($connectorSelector instanceof DataConnectionSelectorInterface) {
            return self::createFromModel($connectorSelector->getWorkbench(), $connectorSelector);
        }
        
        // Otherwise it's a connector prototype selector
        // Check if it's the model loader connection and return it if so. Otherwise create a blank
        // connection from the prototype using the default logic.
        if (self::isMetamodelConnector($connectorSelector)) {
            return $connectorSelector->getWorkbench()->model()->getModelLoader()->getDataConnection();
        } else {
            return parent::createFromSelector($connectorSelector);
        }
    }

    /**
     * Creates an empty instance of the connector specified by the given selector without any specific configuration.
     * 
     * @param WorkbenchInterface $workbench
     * @param string $prototypeSelectorString
     * @param UxonObject $config
     * @return DataConnectionInterface
     */
    public static function createFromPrototype(WorkbenchInterface $workbench, string $prototypeSelectorString, UxonObject $config = null) : DataConnectionInterface
    {
        $selector = SelectorFactory::createDataConnectorSelector($workbench, $prototypeSelectorString);
        if (self::isMetamodelConnector($selector)) {
            return $workbench->model()->getModelLoader()->getDataConnection();
        }
        $instance =  static::createFromSelector($selector);
        if ($config !== null) {
            $instance->importUxonObject($config);
        }
        return $instance;
    }
    
    /**
     * Creates a read-to-use data connection from a connection selector by loading it's model.
     * 
     * @param WorkbenchInterface $workbench
     * @param string|DataConnectionSelectorInterface $uidOrAliasOrSelector
     * @return DataConnectionInterface
     */
    public static function createFromModel(WorkbenchInterface $workbench, $uidOrAliasOrSelector) : DataConnectionInterface
    {
        if ($uidOrAliasOrSelector instanceof DataConnectionSelectorInterface) {
            $selector = $uidOrAliasOrSelector;
        } else {
            $selector = new DataConnectionSelector($workbench, $uidOrAliasOrSelector);
        }
        switch (true) {
            case $selector->isAlias() && strcasecmp($uidOrAliasOrSelector, self::METAMODEL_CONNECTION_ALIAS) === 0:
            case $selector->isUid() && strcasecmp($uidOrAliasOrSelector, self::METAMODEL_CONNECTION_UID) === 0:
                return $workbench->model()->getModelLoader()->getDataConnection();
            default:
                return $workbench->model()->getModelLoader()->loadDataConnection($selector);
        }
    }
    
    /**
     * 
     * @param DataConnectorSelectorInterface $selector
     * @return bool
     */
    protected static function isMetamodelConnector(DataConnectorSelectorInterface $selector) : bool
    {
        switch (true) {
            case $selector->isClassname() && strcasecmp($selector->toString(), '\\' . ModelLoaderConnector::class) === 0:
            case $selector->isFilepath() && strcasecmp(Filemanager::pathNormalize($selector->toString()), Filemanager::pathNormalize(ModelLoaderConnector::class) . '.php') === 0:
                return true;
        }
        return false;
    }
}