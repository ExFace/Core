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
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::setContextModified()
     */
    public function setContextModified(bool $value) : ResultInterface
    {
        if ($value === true) {
            throw new LogicException('Illegal attempt to set positive context modification flag on empty task result! An empty result cannot have modified anything!');
        }
        return parent::setContextModified($value);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::setDataModified()
     */
    public function setDataModified(bool $value) : ResultInterface
    {
        if ($value === true) {
            throw new LogicException('Illegal attempt to set positive data modification flag on empty task result! An empty result cannot have modified anything!');
        }
        return parent::setContextModified($value);
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