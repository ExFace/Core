<?php
namespace exface\Core\Widgets;

use exface\Core\Behaviors\StateMachineState;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;

class StateMenuButton extends MenuButton
{

    private $smb_buttons_set = false;

    private $show_states = [];

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\MenuButton::getButtons()
     */
    public function getButtons(callable $filter_callback = null)
    {
        // Falls am Objekt ein StateMachineBehavior haengt wird versucht den momentanen Status aus
        // dem Objekt auszulesen und die entsprechenden Buttons aus dem Behavior hinzuzufuegen.
        if (! $this->smb_buttons_set) {
            if (is_null($smb = $this->getMetaObject()->getBehaviors()->getByAlias('exface.Core.Behaviors.StateMachineBehavior'))) {
                throw new BehaviorConfigurationError('StateMenuButton: The object ' . $this->getMetaObject()->getAliasWithNamespace() . ' has no StateMachineBehavior attached.');
            }
            
            if (($data_sheet = $this->getPrefillData()) && ($state_column = $data_sheet->getColumnValues($smb->getStateAttributeAlias()))) {
                $current_state = $state_column[0];
            } else {
                $current_state = $smb->getDefaultStateId();
            }
            
            $states = $smb->getStates();
            
            foreach ($smb->getStateButtons($current_state) as $target_state => $smb_button) {
                // Ist show_states leer werden alle Buttons hinzugefuegt (default)
                // sonst wird der Knopf nur hinzugefuegt wenn er in show_states enthalten ist.
                if (empty($this->getShowStates()) || in_array($target_state, $this->getShowStates())) {
                    // Die Eigenschaften des StateMenuButtons werden fuer die einzelnen Buttons
                    // uebernommen. Alle exklusiven Eigenschaften von MenuButton und StateMenuButton
                    // werden entfernt.
                    /* @var $uxon \exface\Core\CommonLogic\UxonObject */
                    $uxon = $this->exportUxonObjectOriginal()->extend(UxonObject::fromAnything($smb_button)->copy());
                    $uxon->unsetProperty('widget_type');
                    $uxon->unsetProperty('show_states');
                    $uxon->unsetProperty('buttons');
                    $uxon->unsetProperty('menu');
                    
                    $button = $this->createButton($uxon);
                    /** @var StateMachineState $stateObject */
                    $stateObject = $states[$target_state];
                    $name = $stateObject->getStateName($this->getMetaObject()->getApp()->getTranslator());
                    if ($name)
                        $button->setCaption($name);
                    
                    $this->addButton($button);
                }
            }
            
            $this->smb_buttons_set = true;
        }
        
        return parent::getButtons($filter_callback);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Button::getCaption()
     */
    public function getCaption()
    {
        $caption = parent::getCaption();
        if (! $caption && ! $this->getHideCaption()) {
            $caption = $this->getWorkbench()->getCoreApp()->getTranslator()->translate('WIDGET.STATEMENUBUTTON.CAPTION');
        }
        return $caption;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\MenuButton::getChildren()
     */
    public function getChildren()
    {
        if (! $this->smb_buttons_set) {
            $this->getButtons();
        }
        return parent::getChildren();
    }

    /**
     * Returns the states that are shown.
     *
     * @return integer[]|string[]
     */
    public function getShowStates()
    {
        return $this->show_states;
    }

    /**
     * Defines a number of states for which transition buttons are shown.
     * By default all buttons defined for the current state are shown.
     *
     * @param integer[]|string[] $value            
     * @return \exface\Core\Widgets\StateMenuButton
     */
    public function setShowStates($value)
    {
        $this->show_states = $value;
        return $this;
    }
}
?>
