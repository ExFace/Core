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
        // Can't render any widget if the workbench was not fully installed yet. 
        // This can happen if the InitDB scripts fail.
        if (false === $this->workbench->isInstalled()) {
            return $record;
        }
        
        $sender = $record['context'][$this->sourceContextKey];
        $debugger = $this->workbench->getDebugger();
        $debugWidgetUxon = null;
        // Let the sender render a debug widget if it can
        if ($sender && ($sender instanceof iCanGenerateDebugWidgets)) {
            // If anything goes wrong, log a fallback-debug-widget for the sender AND
            // the exception produced by the attempt to generate a debug widget
            try {
                $debugWidget     = $sender->createDebugWidget($this->createDebugMessage());
                $debugWidgetUxon = $debugWidget->exportUxonObject();
            } catch (\Throwable $e){
                // If errors occur when creating debug widgets, log these errors separately
                $debugWidgetUxon = new UxonObject([
                    'widget_type' => 'DebugMessage',
                    'tabs' => []
                ]);
                // Create a fallback debug widget for the $sender
                if ($sender instanceof ExceptionInterface){
                    $originalErrorUxon = $this->createHtmlFallback($debugger->printException($sender, true));
                    $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                        'caption' => 'Original error',
                        'widgets' => [
                            $originalErrorUxon->toArray()
                        ]
                    ]));
                }
                // Create a fallback debug widget for the exception from the first rendering attempt
                $renderErrorUxon = $this->createHtmlFallback($debugger->printException($e, true));
                $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                    'caption' => 'Rendering exception',
                    'widgets' => [
                        $renderErrorUxon->toArray()
                    ]
                ]));
            }
        }
        
        // If there is no sender or an problem with it, but the context contains an 
        // error, use the error fallback to create a debug widget
        if ($debugWidgetUxon === null && ($e = $record['context']['exception'] ?? null) instanceof \Throwable){
            $debugWidgetUxon = $this->createHtmlFallback($debugger->printException($e, true));
        } 
        
        // If all the above fails, simply dump the entire record to the debug widget
        if ($debugWidgetUxon === null) {
            $dump = $record;
            unset($dump['formatted']);
            $debugWidgetUxon = $this->createHtmlFallback($debugger->printVariable($dump, true));
        }
        
        $record[$this->targetRecordKey] = $debugWidgetUxon->toJson(true);
        
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
     * @return UxonObject
     */
    protected function createHtmlFallback($html) : UxonObject
    {
        $uxon = new UxonObject([
            'widget_type' => 'Html',
            'width' => '100%',
            'html' => $html
        ]);
        return $uxon;
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