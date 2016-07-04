<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Events\ExFaceEvent;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Events\DataConnectionEvent;
use exface\Core\Events\ActionEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheetEvent;

abstract class EventFactory extends AbstractNameResolverFactory {
	
	/**
	 * Creates a new action from the given name resolver
	 * @param NameResolver $name_resolver
	 * @return ActionInterface
	 */
	public static function create(NameResolverInterface $name_resolver){
		// TODO
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @return ExFaceEvent
	 */
	public static function create_basic_event(Workbench $exface, $event_name){
		$instance = new ExFaceEvent($exface);
		$instance->set_name($event_name);
		return $instance;
	}
	
	/**
	 * 
	 * @param DataConnectionInterface $connection
	 * @return DataConnectionEvent
	 */
	public static function create_data_connection_event(DataConnectionInterface &$connection, $event_name, $current_query = null){
		$exface = $connection->exface();
		$instance = new DataConnectionEvent($exface);
		$instance->set_name($event_name);
		$instance->set_data_connection($connection);
		$instance->set_current_query($current_query);
		return $instance;
	}
	
	/**
	 * 
	 * @param ActionInterface $action
	 * @return ActionEvent
	 */
	public static function create_action_event(ActionInterface &$action, $event_name){
		$exface = $action->exface();
		$instance = new ActionEvent($exface);
		$instance->set_name($event_name);
		$instance->set_action($action);
		return $instance;
	}
	
	public static function create_data_sheet_event(DataSheetInterface &$data_sheet, $event_name){
		$exface = $data_sheet->exface();
		$instance = new DataSheetEvent($exface);
		$instance->set_data_sheet($data_sheet);
		$instance->set_name($event_name);
		return $instance;
	}
}
?>