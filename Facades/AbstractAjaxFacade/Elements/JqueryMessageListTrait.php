<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\MessageList;

/**
 * Generates a pure-HTML list of messages in a <div> container.
 * 
 * @method MessageList getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryMessageListTrait {

    public function buildHtmlMessageList() : string
    {
        $widget = $this->getWidget();
        $messagesHtml = '';
        foreach ($widget->getMessages() as $message) {
            $messagesHtml .= $this->getFacade()->getElement($message)->buildHtml() . "\n";
        }
        
        // The <div> must be a single line because we need the :empty CSS selector to work.
        // It won't work if there is a line break and a tab here
        return <<<HTML
<div class="exf-message-list">{$messagesHtml}</div>

HTML;
    }
}
?>