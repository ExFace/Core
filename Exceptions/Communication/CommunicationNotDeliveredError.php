<?php
namespace exface\Core\Exceptions\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface;

/**
 * Exception thrown if a communication message was rejected (after being sent).
 *
 * @author Andrej Kabachnik
 *        
 */
class CommunicationNotDeliveredError extends RuntimeException implements CommunicationExceptionInterface
{
    private $receipt = null;
    
    /**
     * 
     * @param CommunicationMessageInterface $receipt
     * @param string $errorMessage
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(CommunicationReceiptInterface $receipt, $errorMessage, $alias = null, $previous = null)
    {
        parent::__construct($errorMessage, null, $previous);
        $this->setAlias($alias);
        $this->receipt = $receipt;
    }
    
    /**
     * 
     * @return CommunicationMessageInterface
     */
    public function getCommunicationReceipt() : CommunicationReceiptInterface
    {
        return $this->receipt;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\CommunicationExceptionInterface::getCommunicationMessage()
     */
    public function getCommunicationMessage(): CommunicationMessageInterface
    {
        return $this->receipt->getMessage();
    }
}
