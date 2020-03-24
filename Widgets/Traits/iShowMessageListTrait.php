<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\MessageList;
use exface\Core\Interfaces\Widgets\iShowMessageList;
use exface\Core\Factories\WidgetFactory;

/**
 * This trat contains everything need to implement the iShowMessageList interface.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iShowMessageListTrait {
    
    private $messageList = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::getMessageList()
     */
    public function getMessageList() : MessageList
    {
        if ($this->messageList === null) {
            $this->messageList = WidgetFactory::create($this->getPage(), 'MessageList', $this);
        }
        return $this->messageList;
    }
    
    /**
     * Array of `Message` widgets to display in the form's message list.
     *
     * @uxon-property messages
     * @uxon-type \exface\Core\Widgets\Message[]
     * @uxon-template [{"type": "info", "text": ""}]
     *
     * @param UxonObject $uxon
     * @return MessageList
     *
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::setMessages()
     */
    public function setMessages(UxonObject $uxon) : iShowMessageList
    {
        $this->getMessageList()->setMessages($uxon);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowMessageList::hasMessages()
     */
    public function hasMessages() : bool
    {
        return ! ($this->messageList === null || $this->getMessageList()->isEmpty() === true);
    }
}