<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Traits\ConfirmationWidgetTrait;

/**
 * Simple confirmation message with a button to proceed and a button to cancel
 * 
 */
class ConfirmationMessage extends Message implements ConfirmationWidgetInterface, iUseInputWidget
{
    use ConfirmationWidgetTrait;

    /**
     * Returns the text of the main question
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface::getQuestionText()
     */
    public function getQuestionText() : string
    {
        return $this->getText() ?? $this->getCaption();
    }
}