<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\DataTypes\MessageTypeDataType;

/**
 * A message is a special type of text widget, which is meant to communicate some information to the user.
 * 
 * There are different types of messages: warnings, errors, general information, success messages, and hints. 
 * Messages are displayed alongside other widgets within regular panels - in contrast to toasts or popups, 
 * which are displayed above the main level of widgets.
 *
 * @author Andrej Kabachnik
 *        
 */
class Message extends Text
{

    private $type = NULL;

    /**
     * 
     * @return MessageTypeDataType
     */
    public function getType() : MessageTypeDataType
    {
        if ($this->type === null) {
            $this->type = MessageTypeDataType::INFO($this->getWorkbench());
        }
        return $this->type;
    }

    /**
     * Type of the message: error, warning, info, success, hint.
     * 
     * @uxon-property type
     * @uxon-type [error,warning,info,success,hint]
     * @uxon-default info
     * 
     * @param MessageTypeDataType|string $value
     * @throws WidgetPropertyInvalidValueError
     * @return \exface\Core\Widgets\Message
     */
    public function setType($value)
    {
        if ($value instanceof MessageTypeDataType) {
            $this->type = $value;
        } elseif (is_string($value)) {
            $this->type = MessageTypeDataType::fromValue($this->getWorkbench(), strtoupper($value));
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Unknown message type "' . $value . '"!');
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Text::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('type', $this->getType()->__toString());
        return $uxon;
    }
}
?>