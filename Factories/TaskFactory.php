<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Interfaces\Facades\FacadeInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;
use exface\Core\CommonLogic\Tasks\CliTask;
use exface\Core\Interfaces\Tasks\CliTaskInterface;

/**
 * Creates all kinds of tasks. 
 * 
 * The purpose of this factory is mainly convenience as it bundles creation methods for all
 * kinds of tasks.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskFactory extends AbstractStaticFactory
{
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return \exface\Core\CommonLogic\Tasks\GenericTask
     */
    public static function createEmpty(WorkbenchInterface $workbench) : TaskInterface
    {
        return new GenericTask($workbench);
    }
    
    /**
     * 
     * @param DataSheetInterface $inputData
     * @return TaskInterface
     */
    public static function createFromDataSheet(DataSheetInterface $inputData, WidgetInterface $triggerWidget = null) : TaskInterface
    {
        $task = new GenericTask($inputData->getWorkbench());
        $task->setInputData($inputData);
        if (! is_null($triggerWidget)) {
            $task->setWidgetIdTriggeredBy($triggerWidget->getId());
            $task->setPageSelector(new UiPageSelector($inputData->getWorkbench(), $triggerWidget->getPage()->getAliasWithNamespace()));
        }
        return $task;
    }
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    public static function createHttpTask(FacadeInterface $facade, ServerRequestInterface $request) : HttpTaskInterface
    {
        return new HttpTask($facade->getWorkbench(), $facade, $request);
    }
    
    /**
     * Creates a taks for command line facades.
     * 
     * @param FacadeInterface $facade
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    public static function createCliTask(FacadeInterface $facade, $actionSelector, array $arguments, array $options) : CliTaskInterface
    {
        $task = new CliTask($facade->getWorkbench(), $facade);
        $task->setActionSelector($actionSelector);
        $task->setCliArguments($arguments);
        $task->setCliOptions($options);
        return $task;
    }
}
?>