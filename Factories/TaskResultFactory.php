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

/**
 * Creates all kinds of task results. 
 * 
 * The purpose of this factory is mainly convenience as it bundles creation methods for all
 * kinds of task results.
 * 
 * @author Andrej Kabachnik
 *
 */
class TaskResultFactory extends AbstractFactory
{
    /**
     * 
     * @param TaskInterface $task
     * @param string $message
     * @return TaskResultInterface
     */
    public static function createMessageResult(TaskInterface $task, string $message) : TaskResultInterface
    {
        return new TaskResultMessage($task, $message);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param DataSheetInterface $dataSheet
     * @return TaskResultDataInterface
     */
    public static function createDataResult(TaskInterface $task, DataSheetInterface $dataSheet) : TaskResultDataInterface
    {
        return new TaskResultData($task, $dataSheet);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     * @return TaskResultWidgetInterface
     */
    public static function createWidgetResult(TaskInterface $task, WidgetInterface $widget) : TaskResultWidgetInterface
    {
        return new TaskResultWidget($task, $widget);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $content
     * @return TaskResultTextContentInterface
     */
    public static function createTextContentResult(TaskInterface $task, string $content) : TaskResultTextContentInterface
    {
        return new TaskResultTextContent($task, $content);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param UriInterface $download
     * @return TaskResultFileInterface
     */
    public static function createFileResult(TaskInterface $task, UriInterface $download) : TaskResultFileInterface
    {
        return new TaskResultFile($task, $download);
    }
}
?>