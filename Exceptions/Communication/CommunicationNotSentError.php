<?php
namespace exface\Core\Exceptions\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;

/**
 * Exception thrown if a communication message could not be sent.
 *
 * @author Andrej Kabachnik
 *        
 */
class CommunicationNotSentError extends RuntimeException implements CommunicationExceptionInterface
{
    private $communicationMsg = null;
    
    /**
     * 
     * @param CommunicationMessageInterface $communicationMessage
     * @param string $errorMessage
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(CommunicationMessageInterface $communicationMessage, $errorMessage, $alias = null, $previous = null)
    {
        parent::__construct($errorMessage, null, $previous);
        $this->setAlias($alias);
        $this->communicationMsg = $communicationMessage;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface::getCommunicationMessage()
     */
    public function getCommunicationMessage() : CommunicationMessageInterface
    {
        return $this->communicationMsg;
    }
}
