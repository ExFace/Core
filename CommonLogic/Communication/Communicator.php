<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicatorInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\CommunicationChannelFactory;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationAcknowledgementInterface;

class Communicator implements CommunicatorInterface
{
    private $workbench = null;
    private $channels = [];
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationAcknowledgementInterface[]
     */
    public function send(CommunicationMessageInterface $message) : array
    {
        $acks = [];
        foreach ($this->getChannels($message) as $channel) {
            $acks[] = $channel->send($message);
        }
        return $acks;
    }
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationChannelInterface[]
     */
    protected function getChannels(CommunicationMessageInterface $message) : array
    {
        return [];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
}