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
            $messagesHtml .= $this->getFacade()->getElement($message)->buildHtml();
        }
        
        return <<<HTML

<div class="exf-message-list">
    {$messagesHtml}
</div>

HTML;
    }
}
?>