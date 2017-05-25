<?php
namespace exface\Core\Widgets;

use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;

/**
 * A message is a special type of text widget, which is meant to communicate some information to the user.
 * There are different types of messages: warnings,
 * errors, general information, success messages, etc. Messages are displayed alongside other widgets within regular panels - in contrast to toasts or
 * popups, which are displayed above the main level of widgets.
 *
 * @author Andrej Kabachnik
 *        
 */
class Message extends Text
{

    private $type = NULL;

    public function getType()
    {
        if (! $this->type) {
            $this->type = EXF_MESSAGE_TYPE_INFO;
        }
        return $this->type;
    }

    public function setType($value)
    {
        if ($value == EXF_MESSAGE_TYPE_INFO || $value == EXF_MESSAGE_TYPE_WARNING || $value == EXF_MESSAGE_TYPE_ERROR || $value == EXF_MESSAGE_TYPE_SUCCESS) {
            $this->type = $value;
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Unknown message type "' . $value . '"!');
        }
        return $this;
    }
}
?>