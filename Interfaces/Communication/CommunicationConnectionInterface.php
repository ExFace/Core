<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;

interface CommunicationConnectionInterface extends DataConnectionInterface
{
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @param array $recipients
     * @return CommunicationReceiptInterface
     */
    public function communicate(CommunicationMessageInterface $message) : CommunicationReceiptInterface;
}