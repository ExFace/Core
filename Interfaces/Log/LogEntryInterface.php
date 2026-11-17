<?php
namespace exface\Core\Interfaces\Log;

use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * A single log entry as passed from the logger to its handlers.
 * 
 * Handlers read the data produced by the logger (log ID, exception, sender) through this object instead
 * of well-known array keys. Only the caller-provided context remains a plain array.
 * 
 * @author Georg Bieger
 */
interface LogEntryInterface
{
    /**
     * Context key of the entry inside a Monolog `LogRecord`.
     * 
     * Monolog only accepts array contexts, so handlers that pass entries on to Monolog put the entry itself
     * under this key. Monolog processors and handlers can read it from `$record->context[MONOLOG_CONTEXT_KEY]`
     * to access data, that has no PSR-3 equivalent (e.g. the sender). Remove it before formatting, if the
     * context is serialized (see `ContextFilterProcessor`).
     */
    const MONOLOG_CONTEXT_KEY = 'logEntry';
    
    /**
     * PSR-3 log level, e.g. `LoggerInterface::ERROR`.
     * 
     * @return string
     */
    public function getLevel() : string;
    
    /**
     * @return string
     */
    public function getMessage() : string;
    
    /**
     * Unique ID of this entry - e.g. used to name its debug widget file.
     * 
     * @return string
     */
    public function getId() : string;
    
    /**
     * @return \Throwable|null
     */
    public function getException() : ?\Throwable;
    
    /**
     * The object, that can render a debug widget for this entry.
     * 
     * @return iCanGenerateDebugWidgets|null
     */
    public function getSender() : ?iCanGenerateDebugWidgets;
    
    /**
     * Additional data provided by the caller - without the exception, which is available via `getException()`.
     * 
     * @return array
     */
    public function getContext() : array;
}