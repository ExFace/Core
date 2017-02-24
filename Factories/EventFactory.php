<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Events\ExfaceEvent;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Events\DataConnectionEvent;
use exface\Core\Events\ActionEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Events\WidgetEvent;

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
	 * @return ExfaceEvent
	 */
	public static function create_basic_event(Workbench $exface, $event_name){
		$instance = new ExfaceEvent($exface);
		$instance->set_name($event_name);
		return $instance;
	}
	
	/**
	 * 
	 * @param DataConnectionInterface $connection
	 * @return DataConnectionEvent
	 */
	public static function create_data_connection_event(DataConnectionInterface $connection, $event_name, $current_query = null){
		$exface = $connection->get_workbench();
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
	public static function create_action_event(ActionInterface $action, $event_name){
		$exface = $action->get_workbench();
		$instance = new ActionEvent($exface);
		$instance->set_name($event_name);
		$instance->set_action($action);
		return $instance;
	}
	
	public static function create_data_sheet_event(DataSheetInterface $data_sheet, $event_name){
		$exface = $data_sheet->get_workbench();
		$instance = new DataSheetEvent($exface);
		$instance->set_data_sheet($data_sheet);
		$instance->set_name($event_name);
		return $instance;
	}
	
	public static function create_widget_event(WidgetInterface $widget, $event_name) {
		$exface = $widget->get_workbench();
		$instance = new WidgetEvent($exface);
		$instance->set_name($event_name);
		$instance->set_widget($widget);
		return $instance;
	}
}
?>