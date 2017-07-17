<?php
namespace exface\Core\Factories;

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
use exface\Core\Interfaces\DataSources\DataQueryInterface;

abstract class EventFactory extends AbstractNameResolverFactory
{

    /**
     * Creates a new action from the given name resolver
     *
     * @param NameResolver $name_resolver            
     * @return ActionInterface
     */
    public static function create(NameResolverInterface $name_resolver)
    {
        // TODO
    }

    /**
     *
     * @param exface $exface            
     * @return ExfaceEvent
     */
    public static function createBasicEvent(Workbench $exface, $event_name)
    {
        $instance = new ExfaceEvent($exface);
        $instance->setName($event_name);
        return $instance;
    }

    /**
     *
     * @param DataConnectionInterface $connection            
     * @return DataConnectionEvent
     */
    public static function createDataConnectionEvent(DataConnectionInterface $connection, $event_name, DataQueryInterface $current_query = null)
    {
        $exface = $connection->getWorkbench();
        $instance = new DataConnectionEvent($exface);
        $instance->setName($event_name);
        $instance->setDataConnection($connection);
        if (! is_null($current_query)){
            $instance->setCurrentQuery($current_query);
        }
        return $instance;
    }

    /**
     *
     * @param ActionInterface $action            
     * @return ActionEvent
     */
    public static function createActionEvent(ActionInterface $action, $event_name)
    {
        $exface = $action->getWorkbench();
        $instance = new ActionEvent($exface);
        $instance->setName($event_name);
        $instance->setAction($action);
        return $instance;
    }

    public static function createDataSheetEvent(DataSheetInterface $data_sheet, $event_name)
    {
        $exface = $data_sheet->getWorkbench();
        $instance = new DataSheetEvent($exface);
        $instance->setDataSheet($data_sheet);
        $instance->setName($event_name);
        return $instance;
    }

    public static function createWidgetEvent(WidgetInterface $widget, $event_name)
    {
        $exface = $widget->getWorkbench();
        $instance = new WidgetEvent($exface);
        $instance->setName($event_name);
        $instance->setWidget($widget);
        return $instance;
    }
}
?>