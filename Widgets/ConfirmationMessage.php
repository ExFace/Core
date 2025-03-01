<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;

/**
 * Simple confirmation message with a button to proceed and a button to cancel
 * 
 */
class ConfirmationMessage extends Message implements ConfirmationWidgetInterface
{
    private $buttonContinue = null;

    private $buttonCancel = null;

    /**
     * Returns the text of the main question
     * 
     * @return string
     */
    public function getQuestionText() : string
    {
        return $this->getText() ?? $this->getCaption();
    }

    /**
     * Returns the primary button of the confirmation: accept, continue, OK, or similar.
     * 
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getButtonContinue() : iTriggerAction
    {
        return $this->buttonContinue;
    }

    /**
     * 
     * @return UxonObject
     */
    protected function getButtonContinueDefaults() : UxonObject
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return new UxonObject([
            'widget_type' => 'Button',
            'caption' => $translator->translate('MESSAGE.CONFIRMATION.CONTINUE'),
            'hint' => $translator->translate('MESSAGE.CONFIRMATION.CONTINUE_HINT'),
            'visibility' => WidgetVisibilityDataType::PROMOTED
        ]);
    }

    /**
     * Customize the button to accept the confirmation and continue
     * 
     * @uxon-property button_continue
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"caption": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ConfirmationMessage
     */
    protected function setButtonContinue(UxonObject $uxon) : ConfirmationWidgetInterface
    {
        $uxon = $this->getButtonContinueDefaults()->extend($uxon);
        $this->buttonContinue = WidgetFactory::createFromUxonInParent($this, $uxon);
        return $this;
    }

    /**
     * Returns the negative confirmation button - i.e. "cancel".
     * 
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getButtonCancel() : iTriggerAction
    {
        if ($this->buttonCancel === null) {
            $this->buttonCancel = WidgetFactory::createFromUxonInParent($this, $this->getButtonCancelDefaults());
        }
        return $this->buttonCancel;
    }

    /**
     * 
     * @return UxonObject
     */
    protected function getButtonCancelDefaults() : UxonObject
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return new UxonObject([
            'widget_type' => 'Button',
            'caption' => $translator->translate('MESSAGE.CONFIRMATION.CANCEL'),
            'hint' => $translator->translate('MESSAGE.CONFIRMATION.CANCEL_HINT')
        ]);
    }

    /**
     * Customize the button cancel the operation
     * 
     * @uxon-property button_cancel
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"caption": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ConfirmationMessage
     */
    protected function setButtonCancel(UxonObject $uxon) : ConfirmationWidgetInterface
    {
        $uxon = $this->getButtonCancelDefaults()->extend($uxon);
        $this->buttonCancel = WidgetFactory::createFromUxonInParent($this, $uxon);
        return $this;
    }
}