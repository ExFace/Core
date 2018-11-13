<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\MessageList;

/**
 * Interface for widgets, that can show lists of messages (e.g. results of input validation, etc.)
 * 
 * @author aka
 *
 */
interface iShowMessageList extends WidgetInterface
{
    public function getMessageList() : MessageList;
    
    public function hasMessages() : bool;
}