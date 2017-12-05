<?php
namespace exface\Core\Exceptions\ModelBuilders;

use exface\Core\Exceptions\LogicException;
use exface\Core\Interfaces\Log\LoggerInterface;

class ModelBuilderNotAvailableError extends LogicException {
    
    public function getDefaultAlias()
    {
        return '6Y0U6GM';
    }
    
    public function getDefaultLogLevel()
    {
        return LoggerInterface::NOTICE;
    }
}