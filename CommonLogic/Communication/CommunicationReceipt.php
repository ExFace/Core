<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;

class CommunicationReceipt implements CommunicationReceiptInterface
{
    private $message = null;
    
    private $time = null;
    
    private $connection = null;
    
    private $ignored = false;
    
    public function __construct(CommunicationMessageInterface $message, CommunicationConnectionInterface $connection, \DateTimeInterface $time = null, bool $messageIgnored = false)
    {
        $this->message = $message;
        $this->connection = $connection;
        $this->time = $time ?? new \DateTimeImmutable();
        $this->ignored = $messageIgnored;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getMessage()
     */
    public function getMessage(): CommunicationMessageInterface
    {
        return $this->message;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getSentTime()
     */
    public function getSentTime(): \DateTimeInterface
    {
        return $this->time;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::getConnection()
     */
    public function getConnection() : CommunicationConnectionInterface
    {
        return $this->connection;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationReceiptInterface::isSent()
     */
    public function isSent() : bool
    {
        return $this->ignored === false;
    }
}