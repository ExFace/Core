<?php namespace exface\Core\Interfaces\DataSources;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\AppActionList;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\CommonLogic\Model\ObjectActionList;
use exface\Core\Interfaces\NameResolverInstallerInterface;
use exface\Core\Interfaces\WidgetInterface;

interface ModelLoaderInterface {
	
	/**
	 * Fills the given object with model data (attributes, relations, etc.). 
	 * NOTE: The object must have an id or a qualified alias at this point!
	 * @param Object $object
	 */
	public function load_object(Object $object);
	
	/**
	 * Fills th given data source with model data (query builder, connection configuration, user credentials, etc.)
	 * @param DataSourceInterface $data_source
	 * @param string $data_connection_id_or_alias
	 * @return DataSourceInterface
	 */
	public function load_data_source(DataSourceInterface $data_source, $data_connection_id_or_alias = null);
	
	/**
	 * Returns the data connection, that is used to fetch model data
	 * @return DataConnectionInterface
	 */
	public function get_data_connection();
	
	/**
	 * Sets the data connection to fetch model data from
	 * @param DataConnectionInterface $connection
	 * @return ModelLoaderInterface
	 */
	public function set_data_connection(DataConnectionInterface $connection);
	
	/**
	 * Loads the object specific action definitions into the given meta object.
	 * 
	 * @param ObjectActionList $empty_list
	 * @return ObjectActionList
	 */
	public function load_object_actions(ObjectActionList $empty_list);
	
	/**
	 * Loads the object specific action definitions into the action list.
	 *
	 * @param AppActionList $empty_list
	 * @return AppActionList
	 */
	public function load_app_actions(AppActionList $empty_list);
	
	/**
	 * Loads an action defined in the meta model. Returns NULL if the action is not found
	 * 
	 * @param AppInterface $app
	 * @param string $action_alias
	 * @param WidgetInterface $called_by_widget
	 * @return ActionInterface
	 */
	public function load_action(AppInterface $app, $action_alias, WidgetInterface $called_by_widget = null);
	
	/**
	 * Returns the Installer, that will take care of setting up the model data source, keeping in upto date, etc.
	 * 
	 * @return NameResolverInstallerInterface
	 */
	public function get_installer();
	
}
?>