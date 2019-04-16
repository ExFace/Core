<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\DataSource;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Selectors\DataSourceSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataSourceSelector;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;

abstract class DataSourceFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param DataSourceSelectorInterface $selector
     * @return DataSourceInterface
     */
    public static function createFromSelector(DataSourceSelectorInterface $selector) : DataSourceInterface
    {
        $instance = new DataSource($selector->getWorkbench()->model());
        $instance->setId($selector->toString());
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
        $sourceSelector = new DataSourceSelector($workbench, $sourceSelectorString);
        
        if ($connectionSelectorString !== null) {
            $connectionSelector = new DataConnectionSelector($workbench, $connectionSelectorString);
            $instance = $workbench->model()->getModelLoader()->loadDataSource($sourceSelector, $connectionSelector);
        } else {
            $instance = $workbench->model()->getModelLoader()->loadDataSource($sourceSelector);
        }
        
        return $instance;
    }
}
?>