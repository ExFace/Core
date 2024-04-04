<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicatorInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Factories\CommunicationFactory;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class Communicator implements CommunicatorInterface
{
    private $workbench = null;
    private $channels = [];
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    public function send(CommunicationMessageInterface $envelope) : array
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
    protected function getChannels(CommunicationMessageInterface $envelope) : array
    {
        $result = [];
        if ($chSel = $envelope->getChannelSelector()) {
            if (null === $ch = $this->getChannelLoaded($chSel)) {
                $ch = $this->loadChannel($chSel);
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
    private function loadChannel(CommunicationChannelSelectorInterface $selector) : CommunicationChannelInterface
    {
        $ch = CommunicationFactory::createFromSelector($selector);
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