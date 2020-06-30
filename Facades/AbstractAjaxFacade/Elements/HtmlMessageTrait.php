<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Message;
use exface\Core\DataTypes\MessageTypeDataType;

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
        $text = nl2br($this->getWidget()->getText());
        $output = <<<HTML

				<div class="exf-message {$this->buildCssMessageTypeClass()} {$this->buildCssElementClass()}" style="{$this->buildCssElementStyle()}">
					<div class="exf-message-icon">{$this->buildHtmMessagelIcon()}</div>
					<div class="exf-message-title">{$this->getWidget()->getCaption()}</div>
                    <div class="exf-message-text">{$text}</div>
				</div>
HTML;
        return $output;
    }
    
    protected function buildHtmMessagelIcon() : string
    {
        switch ($this->getWidget()->getType()->__toString()) {
            case MessageTypeDataType::ERROR:
                $output = '<i class="fa fa-exclamation" aria-hidden="true"></i>';
                break;
            case MessageTypeDataType::WARNING:
                $output = '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
                break;
            case MessageTypeDataType::INFO:
                $output = '<i class="fa fa-info-circle" aria-hidden="true"></i>';
                break;
            case MessageTypeDataType::SUCCESS:
                $output = '<i class="fa fa-check-circle" aria-hidden="true"></i>';
                break;
            case MessageTypeDataType::HINT:
                $output = '<i class="fa fa-lightbulb-o" aria-hidden="true"></i>';
                break;
            default:
                $output = '';
        }
        return $output;
    }
    
    protected function buildCssMessageTypeClass() : string
    {
        return mb_strtolower($this->getWidget()->getType()->__toString());
    }
}
?>