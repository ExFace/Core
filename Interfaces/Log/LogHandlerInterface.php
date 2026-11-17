<?php
namespace exface\Core\Interfaces\Log;

interface LogHandlerInterface
{
    /**
     * Handles a single log entry.
     * 
     * @param LogEntryInterface $entry
     * @return void
     */
    public function handle(LogEntryInterface $entry) : void;
}
