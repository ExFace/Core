<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\DataSource;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataSourceSelector;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\Interfaces\Model\ModelInterface;

/**
 * Creates data source instances.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class DataSourceFactory extends AbstractStaticFactory
{
    const METAMODEL_SOURCE_ALIAS = 'METAMODEL_SOURCE';
    
    const METAMODEL_SOURCE_UID = '0x32000000000000000000000000000000';
    
    /**
     * 
     * @param DataSourceSelectorInterface $selector
     * @return DataSourceInterface
     */
    public static function createEmpty(DataSourceSelectorInterface $selector) : DataSourceInterface
    {
        $sourceSelectorString = $selector->toString();
        if (strcasecmp($sourceSelectorString, self::METAMODEL_SOURCE_UID) === 0 || strcasecmp($sourceSelectorString, self::METAMODEL_SOURCE_ALIAS) === 0) {
            return self::createMetamodelDataSource($selector->getWorkbench()->model());
        }
        
        $instance = new DataSource($selector->getWorkbench()->model());
        if ($selector->isUid()) {
            $instance->setId($selector->toString());
        } else {
            // TODO
        }
        return $instance;
    }
    
    /**
     *
     * @param Model $model            
     * @param string $data_source_id            
     * @param string $connectionSelectorString            
     * @return DataSourceInterface
     */
    public static function createFromModel(WorkbenchInterface $workbench, string $sourceSelectorString, string $connectionSelectorString = null)
    {
        if (strcasecmp($sourceSelectorString, self::METAMODEL_SOURCE_UID) === 0 || strcasecmp($sourceSelectorString, self::METAMODEL_SOURCE_ALIAS) === 0) {
            return self::createMetamodelDataSource($workbench->model());
        }
        
        $sourceSelector = new DataSourceSelector($workbench, $sourceSelectorString);
        
        if ($connectionSelectorString !== null) {
            $connectionSelector = new DataConnectionSelector($workbench, $connectionSelectorString);
            $instance = $workbench->model()->getModelLoader()->loadDataSource($sourceSelector, $connectionSelector);
        } else {
            $instance = $workbench->model()->getModelLoader()->loadDataSource($sourceSelector);
        }
        
        return $instance;
    }
    
    /**
     * Creates a new instance of a 
     * @param ModelInterface $model
     * @return DataSourceInterface
     */
    public static function createMetamodelDataSource(ModelInterface $model) : DataSourceInterface
    {
        $source = new DataSource($model);
        $source->setId(self::METAMODEL_SOURCE_UID);
        $source->setQueryBuilderAlias($model->getWorkbench()->getConfig()->getOption('METAMODEL.QUERY_BUILDER'));
        $source->setConnection($model->getModelLoader()->getDataConnection());
        $source->setName('Metamodel Data Source');
        return $source;
    }
}