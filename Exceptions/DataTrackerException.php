<?php

namespace exface\Core\Exceptions;

use exface\Core\Interfaces\Log\LoggerInterface;

class DataTrackerException extends RuntimeException
{
    private array $badData;
    private string $messageWithoutRowNumbers;
    
    public function __construct(string $message, array $badData, string $alias = null, $previous = null)
    {
        $this->badData = $badData;
        $this->messageWithoutRowNumbers = $message;
        
        parent::__construct($this->buildMessageWithRowNumbers($message), $alias, $previous);
    }
    
    public function getMessageWithoutRowNumbers() : string
    {
        return $this->messageWithoutRowNumbers;
    }
    
    public function getBadData() : array
    {
        return $this->badData;
    }
    
    public function getDefaultLogLevel()
    {
        return LoggerInterface::WARNING;
    }

    public function getDefaultAlias()
    {
        return '81VYS9R';
    }
    
    protected function buildMessageWithRowNumbers(string $message) : string
    {
        $lineReport = '(' . implode(', ', array_keys($this->badData)) . ')';
        return 'Rows ' . $lineReport . ': ' . $message;
    }
}