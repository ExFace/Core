<?php
namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\WorkbenchDependantInterface;

interface CommunicatorInterface extends WorkbenchDependantInterface
{
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationReceiptInterface[]
     */
    public function send(CommunicationMessageInterface $message) : array;
}