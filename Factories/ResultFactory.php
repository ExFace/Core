<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Tasks\ResultDataInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;
use exface\Core\CommonLogic\Tasks\ResultMessage;
use exface\Core\CommonLogic\Tasks\ResultData;
use exface\Core\CommonLogic\Tasks\ResultWidget;
use exface\Core\Interfaces\Tasks\ResultTextContentInterface;
use exface\Core\CommonLogic\Tasks\ResultTextContent;
use Psr\Http\Message\UriInterface;
use exface\Core\Interfaces\Tasks\ResultFileInterface;
use exface\Core\CommonLogic\Tasks\ResultFile;
use exface\Core\Interfaces\Tasks\ResultUriInterface;
use exface\Core\CommonLogic\Tasks\ResultUri;
use GuzzleHttp\Psr7\Uri;
use exface\Core\CommonLogic\Tasks\ResultEmpty;

/**
 * Creates all kinds of task results. 
 * 
 * The purpose of this factory is mainly convenience as it bundles creation methods for all
 * kinds of task results.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultFactory extends AbstractFactory
{
    /**
     * 
     * @param TaskInterface $task
     * @param string $message
     * @return ResultInterface
     */
    public static function createMessageResult(TaskInterface $task, string $message) : ResultInterface
    {
        return (new ResultMessage($task))->setMessage($message);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param DataSheetInterface $dataSheet
     * @return ResultDataInterface
     */
    public static function createDataResult(TaskInterface $task, DataSheetInterface $dataSheet, string $message = null) : ResultDataInterface
    {
        $result = new ResultData($task);
        $result->setData($dataSheet);
        if (! is_null($message)) {
            $result->setMessage($message);
        }
        return $result;
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param WidgetInterface $widget
     * @return ResultWidgetInterface
     */
    public static function createWidgetResult(TaskInterface $task, WidgetInterface $widget) : ResultWidgetInterface
    {
        return (new ResultWidget($task))->setWidget($widget);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $content
     * @return ResultTextContentInterface
     */
    public static function createTextContentResult(TaskInterface $task, string $content) : ResultTextContentInterface
    {
        return (new ResultTextContent($task))->setContent($content);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $path
     * @return ResultFileInterface
     */
    public static function createFileResult(TaskInterface $task, string $path) : ResultFileInterface
    {
        return (new ResultFile($task))->setPath($path);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @param string $path
     * @return ResultFileInterface
     */
    public static function createDownloadResult(TaskInterface $task, string $path) : ResultFileInterface
    {
        return (static::createFileResult($task, $path))->setDownloadable(true);
    }
    
    /**
     * 
     * @param TaskInterface $task
     * @return ResultInterface
     */
    public static function createEmptyResult(TaskInterface $task) : ResultInterface
    {
        return new ResultEmpty($task);
    }

    /**
     * 
     * @param TaskInterface $task
     * @param UriInterface|string $uriOrString
     * @return ResultUriInterface
     */
    public static function createUriResult(TaskInterface $task, $uriOrString) : ResultUriInterface
    {
        $result = new ResultUri($task);
        if ($uriOrString instanceof UriInterface) {
            $uri = $uriOrString;
        } else {
            $uri = new Uri($uriOrString);
        }
        $result->setUri($uri);
        return $result;
    }
}
?>