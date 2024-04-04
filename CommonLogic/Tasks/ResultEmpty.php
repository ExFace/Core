<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Exceptions\LogicException;

/**
 * Empty task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultEmpty extends ResultMessage
{    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultInterface::isEmpty()
     */
    public function isEmpty(): bool
    {
        return true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::setMessage()
     */
    public function setMessage(string $string) : ResultInterface
    {
        if ($string !== '') {
            throw new LogicException('Cannot set message on empty task result: it must be really empty!');
        }
        return $this;
    }
}