<?php
namespace exface\Core\CommonLogic\Log\Processors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;

/**
 * Extracts the debug widget from the context and dumps its UXON to a `$record` item.
 * 
 * @author Andrej Kabachnik
 *
 */
class DebugWidgetProcessor 
{
    private $targetRecordKey;
    
    private $sourceContextKey;
    
    private $workbench;
    
    /**
     * 
     * @param array $removeContextKeys
     */
    public function __construct(WorkbenchInterface $workbench, string $sourceContextKey, string $targetRecordKey)
    {
        $this->workbench = $workbench;
        $this->sourceContextKey = $sourceContextKey;
        $this->targetRecordKey = $targetRecordKey;    
    }
    
    /**
     * 
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $sender = $record['context'][$this->sourceContextKey];
        if ($sender && $sender instanceof iCanGenerateDebugWidgets) {
            try {
                $debugWidget     = $sender->createDebugWidget($this->createDebugMessage());
                $debugWidgetData = $debugWidget->exportUxonObject()->toJson(true);
            } catch (\Throwable $e){
                // If errors occur when creating debug widgets, just create an
                // HTML-widget with an exception-dump. If the sender was an error,
                // dump it, otherwise dump the error, that just occured.
                if ($sender instanceof ExceptionInterface){
                    $exception = $sender;
                } else {
                    $exception = $e;
                }
                $debugWidgetData = $this->createHtmlFallback($this->workbench->getDebugger()->printException($exception, true));
            }
            $record[$this->targetRecordKey] = $debugWidgetData;
        } elseif (($e = $record['context']['exception'] ?? null) instanceof \Throwable){
            // If there is no sender, but the context contains an error, use
            // the error fallback to create a debug widget
            $record[$this->targetRecordKey] = $this->createHtmlFallback($this->workbench->getDebugger()->printException($e, true));
        } else {
            // If all the above fails, simply dump the entire record to the debug widget
            $dump = $record;
            unset($dump['formatted']);
            $record[$this->targetRecordKey] = $this->createHtmlFallback($this->workbench->getDebugger()->printVariable($dump, true));
        }
        return $record;
    }
    
    /**
     * Generates a JSON-dump of an HTML-widget with the given contents.
     *
     * This is handy if the regular DebugWidget cannot be created for some
     * reason. Using this fallback it is still possible to create a readable
     * debug widget.
     *
     * @param string $html
     * @return string
     */
    protected function createHtmlFallback($html){
        $uxon = new UxonObject();
        $uxon->setProperty('widget_type', 'Html');
        $uxon->setProperty('html', $html);
        return $uxon->toJson(true);
    }
    
    /**
     *
     * @return \exface\Core\Widgets\DebugMessage
     */
    protected function createDebugMessage()
    {              
        return WidgetFactory::createDebugMessage($this->workbench, MetaObjectFactory::createFromString($this->workbench, 'exface.Core.MESSAGE'));
    }
}