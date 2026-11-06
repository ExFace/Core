<?php
namespace exface\Core\Interfaces\Log;

/**
 * Log handler, that must be registered only once per logger for the same destination.
 * 
 * A "destination" is the place where a handler puts the entries: a specific log file, a database table or an
 * external service. Examples: the daily log file `logs/2026-11-04.log`, the table `MONITOR_ERROR`.
 * 
 * Handlers are injected: any component can create one and append it to the logger. That is fine for
 * handlers without state, but a handler that owns state or writes to a shared destination would produce every
 * entry twice (or corrupt its state) if two instances were registered for the same destination.
 * 
 * Implement this interface to let the logger enforce uniqueness: `LoggerInterface::appendHandler()` ignores a
 * handler if another one with the same key is already registered. The author of the handler decides once, so
 * components that append it do not need to know.
 * 
 * Wrappers (e.g. `BufferingHandler`) should implement it too and return the key of the wrapped handler, so the
 * uniqueness also holds when the handler is wrapped.
 * 
 * @author Georg Bieger
 */
interface UniqueLogHandlerInterface extends LogHandlerInterface
{
    /**
     * Identifies the destination of this handler, e.g. its class plus the path of the log file.
     * 
     * Two handlers with the same key write to the same destination and are considered duplicates. Return NULL
     * if this instance has no uniqueness constraint (e.g. a wrapper around a regular handler).
     * 
     * @return string|null
     */
    public function getUniqueKey() : ?string;
}