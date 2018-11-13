<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Lists messages within other widgets (e.g. Forms).
 *
 * @author Andrej Kabachnik
 *        
 */
class MessageList extends Container
{
    /**
     * 
     * @return array
     */
    public function getMessages() : array
    {
        return $this->getWidgets();
    }
    
    /**
     * Adds a message widget to the list.
     * 
     * @param Message $widget
     * @return MessageList
     */
    public function addMesage(Message $widget) : MessageList
    {
        $this->addWidget($widget);
        return $this;
    }
    
    /**
     * Creates an info message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addInfo(string $text, string $title = null) : MessageList
    {
        return $this->addMessageFromString(EXF_MESSAGE_TYPE_INFO, $text, $title);
    }
    
    /**
     * Creates an error message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addError(string $text, string $title = null) : MessageList
    {
        return $this->addMessageFromString(EXF_MESSAGE_TYPE_ERROR, $text, $title);
    }
    
    /**
     * Creates a warning message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addWarning(string $text, string $title = null) : MessageList
    {
        return $this->addMessageFromString(EXF_MESSAGE_TYPE_WARNING, $text, $title);
    }
    
    /**
     * Creates a success message and appends it to the list.
     * 
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    public function addSuccess(string $text, string $title = null) : MessageList
    {
        return $this->addMessageFromString(EXF_MESSAGE_TYPE_SUCCESS, $text, $title);
    }
    
    /**
     * Creates a message of the given type and appends it to the list.
     * 
     * @param string $type
     * @param string $text
     * @param string $title
     * 
     * @return MessageList
     */
    protected function addMessageFromString(string $type, string $text, string $title = null) : MessageList
    {
        $message = WidgetFactory::createFromUxon($this->getPage(), new UxonObject([
            'widget_type' => 'Message',
            'type' => $type,
            'text' => $text
        ]), $this);
        if ($title !== null) {
            $message->setCaption($title);
        }
        $this->addMesage($message);
        return $this;
    }
}
?>