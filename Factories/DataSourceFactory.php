<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\DataSource;
use exface\Core\Interfaces\DataSources\DataSourceInterface;

abstract class DataSourceFactory extends AbstractFactory {
	
	/**
	 * 
	 * @param Model $model
	 * @return DataSourceInterface
	 */
	public static function create_for_model(Model &$model){
		return new DataSource($model);
	}
	
	/**
	 * 
	 * @param Model $model
	 * @param string $data_source_id
	 * @return DataSourceInterface
	 */
	public static function create_from_id(Model &$model, $data_source_id){
		$instance = static::create_for_model($model);
		$instance->set_id($data_source_id);
		return $instance;
	}
	
	/**
	 * 
	 * @param Model $model
	 * @param string $data_source_id
	 * @param string $data_connection_id_or_alias
	 * @return DataSourceInterface
	 */
	public static function create_for_data_connection(Model &$model, $data_source_id, $data_connection_id_or_alias){
		$instance = static::create_from_id($model, $data_source_id);
		$instance = $model->get_model_loader()->load_data_source($instance, $data_connection_id_or_alias);
		return $instance;
	}
	
}
?>