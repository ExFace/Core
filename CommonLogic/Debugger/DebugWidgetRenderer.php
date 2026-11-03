<?php

namespace exface\Core\CommonLogic\Debugger;

use exface\Core\CommonLogic\Debugger;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\DebugMessage;

/**
 * Renders a debug widget UXON for a log context.
 * 
 * Provides several fallbacks if the regular rendering fails, so that the debug information is always available.
 * 
 * @author Georg Bieger
 */
class DebugWidgetRenderer
{
    const KEY_EXCEPTION = 'exception';

    private WorkbenchInterface $workbench;
    private string $sourceContextKey;

    /**
     *
     * @param WorkbenchInterface $workbench
     * @param string             $sourceContextKey
     */
    public function __construct(
        WorkbenchInterface $workbench,
        string $sourceContextKey
    )
    {
        $this->workbench = $workbench;
        $this->sourceContextKey = $sourceContextKey;
    }

    /**
     * Renders the debug widget UXON for a log context. Always returns a JSON string or throws.
     *
     * Safety net: if regular rendering fails, a minimal widget is built without any workbench
     * dependencies. If even that fails, an exception is thrown so the caller's fallback logger
     * can report it - an invalid or empty detail is never returned.
     *
     * @param array $context the log context (may contain the sender and `exception`)
     * @param array $fallbackData extra data to include in plain dumps, e.g. message, level, channel, datetime
     * @return string
     * @throws \RuntimeException if not even the emergency fallback could be rendered
     */
    public function render(array $context, array $fallbackData = []) : string
    {
        try {
            return $this->renderInternal($context, $fallbackData);
        } catch (\Throwable $e) {
            return $this->renderEmergencyFallback($e, $context);
        }
    }

    /**
     * Regular rendering - may throw, `render()` takes care of the fallback.
     *
     * @param array $context
     * @param array $fallbackData
     * @return string
     */
    protected function renderInternal(array $context, array $fallbackData) : string
    {
        $exception = $context[self::KEY_EXCEPTION] ?? null;

        // Can't render any widget if the workbench was not fully installed yet. 
        // This can happen if the InitDB scripts fail.
        if (false === $this->workbench->isInstalled()) {
            // Try to create a DebugMessage UXON manually
            $debugWidgetUxon = new UxonObject([
                'widget_type' => 'DebugMessage',
                'tabs' => []
            ]);
            
            if ($exception instanceof \Throwable){
                $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                    'caption' => 'Stack trace',
                    'widgets' => [
                        $this->createMarkdownFallback(Debugger::printExceptionAsMarkdown($exception))->toArray()
                    ]
                ]));
            }
            
            $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                'caption' => 'Log entry',
                'widgets' => [
                    $this->createMarkdownFallback($this->printAsMarkdown($this->buildDump($context, $fallbackData)))->toArray()
                ]
            ]));
            
            return $debugWidgetUxon->toJson(true);
        }

        $sender = $context[$this->sourceContextKey] ?? null;
        $debugWidgetUxon = null;
        
        // Let the sender render a debug widget if it can
        if ($sender instanceof iCanGenerateDebugWidgets) {
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
                if ($sender instanceof \Throwable){
                    $originalErrorUxon = $this->createMarkdownFallback(Debugger::printExceptionAsMarkdown($sender));
                    $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                        'caption' => 'Original error',
                        'widgets' => [
                            $originalErrorUxon->toArray()
                        ]
                    ]));
                }
                
                // Create a fallback debug widget for the exception from the first rendering attempt
                $renderErrorUxon = $this->createMarkdownFallback(Debugger::printExceptionAsMarkdown($e));
                $debugWidgetUxon->appendToProperty('tabs', new UxonObject([
                    'caption' => 'Rendering exception',
                    'widgets' => [
                        $renderErrorUxon->toArray()
                    ]
                ]));
            }
        }

        // If there is no sender or a problem with it, but the context contains an 
        // error, use the error fallback to create a debug widget
        if ($debugWidgetUxon === null && $exception instanceof \Throwable){
            $debugWidgetUxon = $this->createMarkdownFallback(Debugger::printExceptionAsMarkdown($exception));
        }

        // If all the above fails, simply dump the entire log entry to the debug widget
        if ($debugWidgetUxon === null) {
            $debugWidgetUxon = $this->createMarkdownFallback($this->printAsMarkdown($this->buildDump($context, $fallbackData)));
        }

        return $debugWidgetUxon->toJson(true);
    }

    /**
     * Last-resort widget built from plain arrays and `json_encode()` only.
     *
     * Deliberately avoids `UxonObject`, `Debugger` and the workbench - any of them may be the
     * reason the regular rendering failed. Uses `\RuntimeException` instead of exface exceptions,
     * because those generate log IDs and debug widgets themselves and could recurse into here.
     *
     * @param \Throwable $renderError
     * @param array $context
     * @return string
     * @throws \RuntimeException
     */
    protected function renderEmergencyFallback(\Throwable $renderError, array $context) : string
    {
        try {
            $text = "Rendering the debug widget failed:\n\n"
                . $this->describeThrowable($renderError);
            
            $original = $context[self::KEY_EXCEPTION] ?? null;
            if ($original instanceof \Throwable) {
                $text .= "\n\nOriginal error:\n\n" . $this->describeThrowable($original);
            }
            
            return json_encode([
                'widget_type' => 'Markdown',
                'width' => '100%',
                'hide_caption' => true,
                'value' => "```\n" . $text . "\n```\n"
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Cannot render debug widget and even the emergency fallback failed: ' . $e->getMessage() 
                . ' (original rendering error: ' . $renderError->getMessage() . ')',
                0,
                $e
            );
        }
    }
    
    /**
     * Combines fallback data (message, level, ...) with the context for plain dumps.
     *
     * @param array $context
     * @param array $fallbackData
     * @return array
     */
    private function buildDump(array $context, array $fallbackData) : array
    {
        return [...$fallbackData, 'context' => $context];
    }
    
    /**
     * Plain-text summary of a throwable without relying on any workbench helpers.
     *
     * @param \Throwable $e
     * @return string
     */
    private function describeThrowable(\Throwable $e) : string
    {
        return get_class($e) . ': ' . $e->getMessage() . "\n"
            . 'in ' . $e->getFile() . ':' . $e->getLine() . "\n\n"
            . $e->getTraceAsString();
    }

    /**
     *
     * @param array $context
     * @return string
     */
    protected function printAsMarkdown(mixed $context) : string
    {
        // Do NOT use print_r() here as it will cause recursion and memory overflows!
        return "```\n" . Debugger::printVariable($context, false) . "\n```\n";
    }

    /**
     * Generates a JSON-dump of an HTML-widget with the given contents.
     *
     * This is handy if the regular DebugWidget cannot be created for some
     * reason. Using this fallback it is still possible to create a readable
     * debug widget.
     *
     * @param string $markdown
     * @return UxonObject
     */
    protected function createMarkdownFallback(string $markdown) : UxonObject
    {
        return new UxonObject([
            'widget_type' => 'Markdown',
            'width' => '100%',
            'hide_caption' => true,
            'value' => $markdown
        ]);
    }

    /**
     *
     * @return DebugMessage
     */
    protected function createDebugMessage() : DebugMessage
    {
        return WidgetFactory::createDebugMessage($this->workbench, MetaObjectFactory::createFromString($this->workbench, 'exface.Core.MESSAGE'));
    }
}