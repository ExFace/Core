<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Interfaces\Communication\CommunicationReceiptInterface;
use exface\Core\Interfaces\Communication\CommunicationConnectionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

class CommunicationReceipt implements CommunicationReceiptInterface
{
    private $message = null;
    
    private $time = null;
    
    private $connection = null;
    
    private $ignored = false;
    
    private $debugCallback = null;
    
    public function __construct(CommunicationMessageInterface $message, CommunicationConnectionInterface $connection, callable $debugWidgetCallback = null, \DateTimeInterface $time = null, bool $messageIgnored = false)
    {
        $this->message = $message;
        $this->connection = $connection;
        $this->time = $time ?? new \DateTimeImmutable();
        $this->ignored = $messageIgnored;
        $this->debugCallback = $debugWidgetCallback;
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $message = $this->getMessage();
        if ($message instanceof iCanGenerateDebugWidgets) {
            $debug_widget = $message->createDebugWidget($debug_widget);
        }
        if ($this->debugCallback !== null) {
            $callback = $this->debugCallback;
            $debug_widget = $callback($debug_widget);
        }
        return $debug_widget;
    }
}