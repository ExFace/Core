<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Message;

/**
 * Generates a pure-HTML list of messages in a <div> container.
 * 
 * @method Message getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait HtmlMessageTrait {

    protected function buildHtmlMessage() : string
    {
        $output = '
				<div class="exf-message ' . $this->buildCssMessageTypeClass() . '">
					<div class="exf-message-icon">' . $this->buildHtmMessagelIcon() . '</div>
					<div class="exf-message-title">' . $this->getWidget()->getCaption() . '</div>
                    <div class="exf-message-text">' . $this->getWidget()->getText() . '</div>
				</div>';
        return $output;
    }
    
    protected function buildHtmMessagelIcon() : string
    {
        switch ($this->getWidget()->getType()) {
            case EXF_MESSAGE_TYPE_ERROR:
                $output = '<i class="fa fa-exclamation" aria-hidden="true"></i>';
                break;
            case EXF_MESSAGE_TYPE_WARNING:
                $output = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
                break;
            case EXF_MESSAGE_TYPE_INFO:
                $output = '<i class="fa fa-info-circle" aria-hidden="true"></i>';
                break;
            case EXF_MESSAGE_TYPE_SUCCESS:
                $output = '<i class="fa fa-check-circle" aria-hidden="true"></i>';
                break;
        }
        return $output;
    }
    
    protected function buildCssMessageTypeClass() : string
    {
        switch ($this->getWidget()->getType()) {
            case EXF_MESSAGE_TYPE_ERROR:
                $output = 'error';
                break;
            case EXF_MESSAGE_TYPE_WARNING:
                $output = 'warning';
                break;
            case EXF_MESSAGE_TYPE_INFO:
                $output = 'info';
                break;
            case EXF_MESSAGE_TYPE_SUCCESS:
                $output = 'success';
                break;
        }
        return $output;
    }
}
?>