<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Widgets\Button;

/**
 * Common methods for confirmation widgets
 * 
 */
trait ConfirmationWidgetTrait
{
    private $buttonContinue = null;

    private $buttonCancel = null;

    private $disabledIfNoChanges = false;

    private $action = null;

    public function setAction(ActionInterface $action) : ConfirmationWidgetInterface
    {
        $this->action = $action;
        return $this;
    }

    protected function getAction() : ?ActionInterface
    {
        return $this->action;
    }

    /**
     * Returns the primary button of the confirmation: accept, continue, OK, or similar.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface::getButtonContinue()
     */
    public function getButtonContinue() : iTriggerAction
    {
        if ($this->buttonContinue === null) {
            $btnUxon = $this->getButtonContinueDefaults();
            if (null !== $action = $this->getAction()) {
                $btnUxon->setProperty('caption', $action->getName());
            }
            $this->setButtonContinue($btnUxon);
                        
        }
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
     * @return ConfirmationWidgetInterface
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface::getButtonCancel()
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
     * @return ConfirmationWidgetInterface
     */
    protected function setButtonCancel(UxonObject $uxon) : ConfirmationWidgetInterface
    {
        $uxon = $this->getButtonCancelDefaults()->extend($uxon);
        $this->buttonCancel = WidgetFactory::createFromUxonInParent($this, $uxon);
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface::getDisabledIfNoChanges()
     */
    public function getDisabledIfNoChanges() : bool
    {
        return $this->disabledIfNoChanges;
    }

    /**
     * Skip this confirmation if the input widget does not have any changes
     * 
     * @uxon-property disabled_if_no_changes
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return ConfirmationWidgetInterface
     */
    protected function setDisabledIfNoChanges(bool $trueOrFalse) : ConfirmationWidgetInterface
    {
        $this->disabledIfNoChanges = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return WidgetInterface
     */
    public function getTriggerButton() : Button
    {
        return $this->getParent();
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseInputWidget::getInputWidget()
     */
    public function getInputWidget() : WidgetInterface
    {
        return $this->getTriggerButton()->getInputWidget();
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseInputWidget::setInputWidget()
     */
    public function setInputWidget(WidgetInterface $widget) : iUseInputWidget
    {
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface::getActionConfirmed()
     */
    public function getActionConfirmed() : ActionInterface
    {
        return $this->getTriggerButton()->getAction();
    }
}