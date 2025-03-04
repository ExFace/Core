<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\EntityList;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Actions\ActionConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\ActionConfirmationListInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 * @method \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface get()
 * @method \exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface getFirst()
 * @method \exface\Core\Interfaces\Actions\ActionConfirmationListInterface|\exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface|DataCheckInterface[] getIterator()
 *        
 */
class ActionConfirmationList extends EntityList implements ActionConfirmationListInterface
{
    private $disabled = false;

    private $confirmationForAction = null;

    private $confirmationForUnsavedData = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::getAction()
     */
    public function getAction() : ActionInterface
    {
        return $this->getParent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::disableAll()
     */
    public function setDisabled(bool $trueOrFalse): ActionConfirmationListInterface
    {
        $this->disabled = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::isDisabled()
     */
    public function isDisabled() : bool
    {
        return $this->disabled;
    }

    /**
     * Make the action ask for confirmation when its button is pressed
     * 
     * @uxon-property confirmation_for_action
     * @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
     * @uxon-template {"widget_type": "ConfirmationMessage", "text": ""}
     * 
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::addConfirmationFromUxon()
     */
    public function addFromUxon(UxonObject $uxon) : ActionConfirmationListInterface
    {
        if ($this->getAction()->isDefinedInWidget()) {
            $parent = $this->getAction()->getWidgetDefinedIn();
            if (! $uxon->hasProperty('button_continue')) {
                $uxon->setProperty('button_continue', new UxonObject([
                    'caption' => $this->getAction()->getName()
                ]));
            }
            $this->confirmationForAction = WidgetFactory::createFromUxonInParent($parent, $uxon, 'ConfirmationMessage');
        } else {
            // TODO what here?
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::getConfirmationForAction()
     */
    public function getConfirmationForAction() : ?ConfirmationWidgetInterface
    {
        if ($this->confirmationForAction === false) {
            return null;
        }
        return $this->confirmationForAction;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::hasConfirmationForAction()
     */
    public function hasConfirmationForAction() : bool
    {
        return ($this->confirmationForAction instanceof UxonObject);
    }

    /**
     * Make the action warn the user if it is to be performed when unsaved changes are still visible
     * 
     * @uxon-property confirmation_for_unsaved_data
     * @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
     * @uxon-template {"widget_type": "ConfirmationMessage", "text": ""}
     * 
     * @param mixed $uxonOrBoolOrString
     * @throws \exface\Core\Exceptions\Actions\ActionConfigurationError
     * @return ActionConfirmationList
     */
    public function setConfirmationForUnsavedChanges($uxonOrBoolOrString) : ActionConfirmationListInterface
    {
        switch (true) {
            case $uxonOrBoolOrString === false:
            case $uxonOrBoolOrString === true:
                $this->confirmationForUnsavedData = $uxonOrBoolOrString;
                return $this;
            case $uxonOrBoolOrString instanceof UxonObject:
                $uxon = $uxonOrBoolOrString;
                break;
            case is_string($uxonOrBoolOrString):
                $uxon = new UxonObject([
                    'text' => $uxonOrBoolOrString
                ]);
                break;
            default:
                throw new ActionConfigurationError($this->getAction(), 'Invalid value for confirmation_for_unsaved_changes in action');
        }

        if ($this->confirmationForUnsavedData instanceof ConfirmationWidgetInterface) {
            $this->remove($this->confirmationForUnsavedData);
            $this->confirmationForUnsavedData = null;
        }

        if ($this->getAction()->isDefinedInWidget()) {
            $parent = $this->getAction()->getWidgetDefinedIn();
            $this->confirmationForUnsavedData = WidgetFactory::createFromUxonInParent($parent, $uxon, 'ConfirmationMessage');
            $this->add($this->confirmationForUnsavedData, 0);
        } else {
            // TODO what here?
        }
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::getConfirmationForUnsavedChanges()
     */
    public function getConfirmationForUnsavedChanges() : ?ConfirmationWidgetInterface
    {
        if ($this->confirmationForUnsavedData === false) {
            return null;
        }
        if (($this->confirmationForUnsavedData ?? true) === true && $this->hasConfirmationForUnsavedChanges()) {
            $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
            $this->setConfirmationForUnsavedChanges(new UxonObject([
                'widget_type' => 'ConfirmationMessage',
                'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.TITLE'),
                'text' => $translator->translate('MESSAGE.DISCARD_CHANGES.TEXT'),
                'icon' => Icons::QUESTION_CIRCLE_O,
                'button_continue' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CONTINUE')
                ],
                'button_cancel' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CANCEL')
                ]
            ]));
        }
        return $this->confirmationForUnsavedData;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::hasConfirmationForUnsavedChanges()
     */
    public function hasConfirmationForUnsavedChanges(?bool $default = false) : ?bool
    {
        if ($this->confirmationForUnsavedData === false) {
            return false;
        }
        if ($this->confirmationForUnsavedData !== null) {
            return true;
        }

        return $default;
    }
}