<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\CommonLogic\EntityList;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\MessageTypeDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Actions\ActionConfirmationListInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\WidgetInterface;
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
    private $disableAll = false;

    private $disableForUnsavedChanges = true;

    private $disableForAction = false;
    
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
    public function disableAll(bool $trueOrFalse): ActionConfirmationListInterface
    {
        $this->disableAll = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::disableConfirmationsForUnsavedChanges()
     */
    public function disableConfirmationsForUnsavedChanges(bool $trueOrFalse): ActionConfirmationListInterface
    {
        $this->disableForUnsavedChanges = $trueOrFalse;
        foreach ($this->getConfirmationsForUnsavedChanges() as $conf) {
            $conf->setDisabled($trueOrFalse);
        }
        return $this;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::disableConfirmationsForAction()
     */
    public function disableConfirmationsForAction(bool $trueOrFalse): ActionConfirmationListInterface
    {
        $this->disableForAction = $trueOrFalse;
        foreach ($this->getConfirmationsForAction() as $conf) {
            $conf->setDisabled($trueOrFalse);
        }
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isDisabled() : bool
    {
        return $this->disableAll;
    }

    /**
     * 
     * @return bool
     */
    public function isDisabledForUnsavedChanges() : bool
    {
        return $this->disableForUnsavedChanges;
    }

    /**
     * 
     * @return bool
     */
    public function isDisabledForAction() : bool
    {
        return $this->disableForAction;
    }

    /**
     * Make the action ask for confirmation when its button is pressed
     * 
     * @uxon-property confirmation_for_action
     * @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
     * @uxon-template {"widget_type": "ConfirmationMessage", "text": ""}
     * 
     * @see \exface\Core\Interfaces\Actions\ActionConfirmationListInterface::addFromUxon()
     */
    public function addFromUxon(UxonObject $uxon) : ActionConfirmationListInterface
    {
        $this->add($this->createConfirmation($uxon));
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::getConfirmationsForAction()
     */
    public function getConfirmationsForAction() : self
    {
        return $this->filter(function(ConfirmationWidgetInterface $widget){
            return $widget->getDisabledIfNoChanges() === false;
        });
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::getConfirmationsForUnsavedChanges()
     */
    public function getConfirmationsForUnsavedChanges() : self
    {
        $list = $this->filter(function(ConfirmationWidgetInterface $widget){
            return $widget->getDisabledIfNoChanges() === true;
        });
        if ($this->isDisabledForUnsavedChanges() === false) {
            $conf = $this->createConfirmationForUnsavedChanges();
            $this->prepend($conf);
            $list->add($conf);
        }
        return $list;
    }

    /**
     * 
     * @param \exface\Core\CommonLogic\UxonObject|null $uxon
     * @return WidgetInterface
     */
    public function createConfirmationForUnsavedChanges(UxonObject $uxon = null) : ConfirmationWidgetInterface
    {
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        if ($uxon === null || ! $uxon->hasProperty('widget_type')) {
            $default = new UxonObject([
                'widget_type' => 'ConfirmationMessage',
                'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.TITLE'),
                'text' => $translator->translate('MESSAGE.DISCARD_CHANGES.TEXT'),
                'type' => MessageTypeDataType::QUESTION,
                'disabled_if_no_changes' => true,
                'button_continue' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CONTINUE')
                ],
                'button_cancel' => [
                    'caption' => $translator->translate('MESSAGE.DISCARD_CHANGES.CANCEL')
                ]
            ]);   
            if ($uxon !== null) {
                $uxon = $default->extend($uxon);
            } else {
                $uxon = $default;
            }
        }
        return $this->createConfirmation($uxon);
    }

    /**
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return WidgetInterface
     */
    public function createConfirmation(UxonObject $uxon) : ConfirmationWidgetInterface
    {
        $confirmation = WidgetFactory::createFromUxonInParent($this->getAction()->getWidgetDefinedIn(), $uxon, 'ConfirmationMessage');
        $confirmation->setAction($this->getAction());
        return $confirmation;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::isPossible()
     */
    public function isPossible() : bool
    {
        return $this->getAction()->isDefinedInWidget();
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\EntityList::add()
     */
    public function add($entity, $key = null) 
    {
        parent::add($entity, $key);
        if ($this->isDisabled()) {
            $entity->setDisabled(true);
        }
        if ($this->isDisabledForAction() && ! $this->isForUnsavedChanges($entity)) {
            $entity->setDisabled(true);
        }
        if ($this->isDisabledForUnsavedChanges() && $this->isForUnsavedChanges($entity)) {
            $entity->setDisabled(true);
        }
        return $this;
    }

    protected function isForUnsavedChanges(ConfirmationWidgetInterface $confirmation) : bool
    {
        return $confirmation->getDisabledIfNoChanges() === true;
    }
}