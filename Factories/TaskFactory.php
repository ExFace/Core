<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\TaskResultDataInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\TaskResultWidgetInterface;
use exface\Core\CommonLogic\Tasks\TaskResultMessage;
use exface\Core\CommonLogic\Tasks\TaskResultData;
use exface\Core\CommonLogic\Tasks\TaskResultWidget;
use exface\Core\Interfaces\Tasks\TaskResultTextContentInterface;
use exface\Core\CommonLogic\Tasks\TaskResultTextContent;
use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Tasks\TaskResultFileInterface;
use exface\Core\CommonLogic\Tasks\TaskResultFile;
use exface\Core\Interfaces\Tasks\TaskResultUriInterface;
use exface\Core\CommonLogic\Tasks\TaskResultUri;
use GuzzleHttp\Psr7\Uri;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\Interfaces\Templates\TemplateInterface;
use Psr\Http\Message\ServerRequestInterface;
use exface\Core\CommonLogic\Tasks\HttpTask;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\CommonLogic\Selectors\UiPageSelector;

/**
 * Creates all kinds of tasks. 
 * 
 * The purpose of this factory is mainly convenience as it bundles creation methods for all
 * kinds of tasks.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskFactory extends AbstractFactory
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
     * @param TemplateInterface $template
     * @param ServerRequestInterface $request
     * @return HttpTaskInterface
     */
    public static function createHttpTask(TemplateInterface $template, ServerRequestInterface $request) : HttpTaskInterface
    {
        return new HttpTask($template->getWorkbench(), $template, $request);
    }
}
?>