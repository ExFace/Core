<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;

interface ConfirmationWidgetInterface extends WidgetInterface
{
    /**
     * 
     * @return \exface\Core\DataTypes\MessageTypeDataType
     */
    public function getType() : MessageTypeDataType;

    public function getQuestionText() : string;

    public function getButtonContinue() : iTriggerAction;
    
    public function getButtonCancel() : iTriggerAction;

    public function getDisabledIfNoChanges() : bool;

    public function getActionConfirmed() : ActionInterface;
}