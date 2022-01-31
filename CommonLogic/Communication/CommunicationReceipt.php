<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;

class CommunicationReceipt implements CommunicationReceiptInterface
{
    private $message = null;
    
    private $time = null;
    
    private $connection = null;
    
    public function __construct(CommunicationMessageInterface $message, CommunicationConnectionInterface $connection, \DateTimeInterface $time = null)
    {
        $this->message = $message;
        $this->connection = $connection;
        $this->time = $time ?? new \DateTimeImmutable();
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
}