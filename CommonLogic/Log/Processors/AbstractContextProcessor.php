<?php

namespace exface\Core\CommonLogic\Log\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Base class for context processors, which can modify the context and extra fields of a log record.
 * 
 * @author Georg Bieger
 */
abstract class AbstractContextProcessor 
    implements ProcessorInterface
{
    /**
     * @param LogRecord $record
     * @return LogRecord
     */
    public final function __invoke(LogRecord $record) : LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;
        
        $this->invokeInternal($context, $extra);
        
        return $record->with(
            context: $context,
            extra: $extra
        );
    }

    /**
     * Implement this method to modify the context and extra arrays as needed.
     * You can use the extractValue and setValue helper methods to manipulate the arrays.
     * Since they work on references, you can modify the arrays directly without 
     * needing to return them.
     * 
     * @param array $context
     * @param array $extra
     * @return void
     */
    protected abstract function invokeInternal(array &$context, array &$extra) : void;

    /**
     * Sets a value in the given array by key.
     * 
     * The array will only be changed if it does NOT contain `$key` or if `$overwrite` is TRUE.
     * 
     * @param array  $to
     * @param string $key
     * @param mixed  $value
     * @param bool   $overwrite
     * @return void
     */
    protected function setValue(
        array &$to,
        string $key,
        mixed $value,
        bool $overwrite = false
    ) : void
    {
        if($overwrite || !array_key_exists($key, $to)) {
            $to[$key] = $value;
        }
    }
}