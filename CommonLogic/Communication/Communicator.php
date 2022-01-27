<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicatorInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\CommunicationChannelFactory;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\EnvelopeInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;

class Communicator implements CommunicatorInterface
{
    private $workbench = null;
    private $channels = [];
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function send(EnvelopeInterface $envelope) : array
    {
        $acks = [];
        foreach ($this->getChannels($envelope) as $channel) {
            $acks[] = $channel->send($envelope);
        }
        return $acks;
    }
    
    /**
     * 
     * @param CommunicationMessageInterface $message
     * @return CommunicationChannelInterface[]
     */
    protected function getChannels(EnvelopeInterface $envelope) : array
    {
        $result = [];
        if ($chSel = $envelope->getChannelSelector()) {
            if (null === $ch = $this->getChannelLoaded($chSel)) {
                $ch = $this->loadChanne($chSel);
            }
            $result[] = $ch;
        }
        return $result;
    }
    
    /**
     * 
     * @param CommunicationChannelSelectorInterface $selector
     * @return CommunicationChannelInterface|NULL
     */
    private function getChannelLoaded(CommunicationChannelSelectorInterface $selector) : ?CommunicationChannelInterface
    {
        return $this->channels[$selector->toString()];
    }
    
    /**
     * 
     * @param CommunicationChannelSelectorInterface $selector
     * @return CommunicationChannelInterface
     */
    private function loadChanne(CommunicationChannelSelectorInterface $selector) : CommunicationChannelInterface
    {
        $ch = CommunicationChannelFactory::createFromSelector($selector);
        $this->channels[$selector->toString()] = $ch;
        return $ch;
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