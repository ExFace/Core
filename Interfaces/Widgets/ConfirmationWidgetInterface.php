<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;

interface ConfirmationWidgetInterface extends WidgetInterface
{
    public function getQuestionText() : string;

    public function getButtonContinue() : iTriggerAction;
    
    public function getButtonCancel() : iTriggerAction;
}