<?php
namespace exface\Core\CommonLogic\Log\Monolog;

use Monolog\Formatter\FormatterInterface;

/**
 * Makes a monolog handler output `$record['message']` only - nothing else.
 * 
 * @author Andrej Kabachnik
 *
 */
class MessageOnlyFormatter implements FormatterInterface
{

    /**
     * Formats a log record.
     *
     * @param array $record
     *            A record to format
     *            
     * @return mixed The formatted record
     */
    public function format(array $record)
    {
        if (isset($record['message']))
            return $record['message'];
        
        return '[no message found]';
    }

    /**
     * Formats a set of log records.
     *
     * @param array $records
     *            A set of records to format
     *            
     * @return mixed The formatted set of records
     */
    public function formatBatch(array $records)
    {
        $message = '';
        
        if (! is_array($records))
            return $message;
        
        foreach ($records as $record) {
            $message .= $this->format($record);
        }
        
        return $message;
    }
}
