<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\DataSource;
use exface\Core\Interfaces\DataSources\DataSourceInterface;

abstract class DataSourceFactory extends AbstractFactory
{

    /**
     *
     * @param Model $model            
     * @return DataSourceInterface
     */
    public static function createForModel(Model $model)
    {
        return new DataSource($model);
    }

    /**
     *
     * @param Model $model            
     * @param string $data_source_id            
     * @return DataSourceInterface
     */
    public static function createFromId(Model $model, $data_source_id)
    {
        $instance = static::createForModel($model);
        $instance->setId($data_source_id);
        return $instance;
    }

    /**
     *
     * @param Model $model            
     * @param string $data_source_id            
     * @param string $data_connection_id_or_alias            
     * @return DataSourceInterface
     */
    public static function createForDataConnection(Model $model, $data_source_id, $data_connection_id_or_alias)
    {
        $instance = static::createFromId($model, $data_source_id);
        $instance = $model->getModelLoader()->loadDataSource($instance, $data_connection_id_or_alias);
        return $instance;
    }
}
?>