<?php

namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\AbstractBehavior;
use exface\Core\Events\WidgetEvent;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Exceptions\Behaviors\StateMachineUpdateException;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 * A behavior that defines states and transitions between these states for an objects.
 *
 * @author SFL
 */
class StateMachineBehavior extends AbstractBehavior
{

    private $state_attribute_alias = null;

    private $default_state = null;

    private $uxon_states = null;

    private $states = null;

    private $progress_bar_color_map = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractBehavior::register()
     */
    public function register()
    {
        $this->getWorkbench()
            ->eventManager()
            ->addListener($this->getObject()
            ->getAliasWithNamespace() . '.Widget.Prefill.After', array(
            $this,
            'setWidgetStates'
        ));
        $this->getWorkbench()
            ->eventManager()
            ->addListener($this->getObject()
            ->getAliasWithNamespace() . '.DataSheet.UpdateData.Before', array(
            $this,
            'checkForConflictsOnUpdate'
        ));
        $this->setRegistered(true);
    }

    /**
     * Returns the state attribute alias.
     *
     * @throws BehaviorConfigurationError
     * @return string
     */
    public function getStateAttributeAlias()
    {
        if (is_null($this->state_attribute_alias)) {
            throw new BehaviorConfigurationError($this->getObject(), 'Cannot initialize StateMachineBehavior for "' . $this->getObject()->getAliasWithNamespace() . '": state_attribute_alias not set in behavior configuration!', '6TG2ZFI');
        }
        return $this->state_attribute_alias;
    }

    /**
     * Defines the attribute alias, that holds the state id.
     *
     * @uxon-property state_attribute_alias
     * @uxon-type string
     *
     * @param string $value            
     * @return \exface\Core\Behaviors\StateMachineBehavior
     */
    public function setStateAttributeAlias($value)
    {
        $this->state_attribute_alias = $value;
        return $this;
    }

    /**
     * Determines the state attribute from the alias and the attached object and
     * returns it.
     *
     * @return \exface\Core\CommonLogic\Model\Attribute
     */
    public function getStateAttribute()
    {
        return $this->getObject()->getAttribute($this->getStateAttributeAlias());
    }

    /**
     * Returns the default state.
     *
     * @return StateMachineState
     */
    public function getDefaultState()
    {
        return $this->getState($this->getDefaultStateId());
    }

    /**
     * Defines the default state id, which is to be used if no object state can be determined
     * (e.g.
     * to determine possible values for the StateMenuButton).
     *
     * @uxon-property default_state
     * @uxon-type number
     *
     * @param integer|string|StateMachineState $value            
     * @return \exface\Core\Behaviors\StateMachineBehavior
     */
    public function setDefaultState($value)
    {
        if ($value instanceof StateMachineState) {
            if (! array_key_exists($value->getStateId(), $this->getStates())) {
                $this->addState($value);
            }
            $this->default_state = $value->getStateId();
        } elseif (is_int($value) || is_string($value)) {
            $this->default_state = $value;
        } else {
            throw new BehaviorConfigurationError($this->getObject(), 'Can not set default state for "' . $this->getObject()->getAliasWithNamespace() . '": the argument passed to set_default_state() is neither a StateMachineState nor an integer nor a string!', '6TG2ZFI');
        }
        
        return $this;
    }

    /**
     * Returns the default state id.
     *
     * @throws BehaviorConfigurationError
     * @return integer|string
     */
    public function getDefaultStateId()
    {
        if (is_null($this->default_state)) {
            if (count($states = $this->getStates()) > 0) {
                $this->default_state = reset($states)->getStateId();
            } else {
                throw new BehaviorConfigurationError($this->getObject(), 'The default state cannot be determined for "' . $this->getObject()->getAliasWithNamespace() . '": neither state definitions nor a default state are set!', '6TG2ZFI');
            }
        }
        return $this->default_state;
    }

    /**
     * Returns an array of StateMachineState objects.
     *
     * @return StateMachineState[]
     */
    public function getStates()
    {
        return $this->states;
    }

    /**
     * Defines the states of the state machine.
     *
     * The states are set by a JSON object or array with state ids for keys and an objects describing the state for values.
     *
     * Example:
     * "states": {
     * "10": {
     * "buttons": {
     * "10": {
     * "caption": "20 Annahme bestätigen",
     * "action": {
     * "alias": "exface.Core.UpdateData",
     * "input_data_sheet": {
     * "object_alias": "alexa.RMS.CUSTOMER_COMPLAINT",
     * "columns": [
     * {
     * "attribute_alias": "STATE_ID",
     * "formula": "=NumberValue('20')"
     * },
     * {
     * "attribute_alias": "TS_UPDATE"
     * }
     * ]
     * }
     * }
     * }
     * },
     * "disabled_attributes_aliases": [
     * "COMPLAINT_NO"
     * ],
     * "transitions": [
     * 10,
     * 20,
     * 30,
     * 50,
     * 60,
     * 70,
     * 90,
     * 99
     * ]
     * }
     * }
     *
     * @uxon-property states
     * @uxon-type object
     *
     * @param UxonObject|StateMachineState[] $value            
     * @throws BehaviorConfigurationError
     * @return \exface\Core\Behaviors\StateMachineBehavior
     */
    public function setStates($value)
    {
        $this->uxon_states = UxonObject::fromAnything($value);
        
        if ($value instanceof UxonObject) {
            $this->states = [];
            $states = get_object_vars($this->uxon_states);
            foreach ($states as $state => $uxon_smstate) {
                $smstate = new StateMachineState();
                $smstate->setStateId($state);
                if ($uxon_smstate) {
                    try {
                        $uxon_smstate->mapToClassSetters($smstate);
                    } catch (UxonMapError $e) {
                        throw new BehaviorConfigurationError($this->getObject(), 'Cannot load UXON configuration for state machine state. ' . $e->getMessage(), '6TG2ZFI', $e);
                    }
                }
                $this->addState($smstate);
            }
        } elseif (is_array($value)) {
            $this->states = $value;
        } else {
            throw new BehaviorConfigurationError($this->getObject(), 'Can not set states for "' . $this->getObject()->getAliasWithNamespace() . '": the argument passed to set_states() is neither an UxonObject nor an array!', '6TG2ZFI');
        }
        
        return $this;
    }

    /**
     * Returns the StateMachineState object belonging to the passed state id.
     *
     * @param integer|string $state_id            
     * @return StateMachineState
     */
    public function getState($state_id)
    {
        return $this->states[$state_id];
    }

    /**
     * Adds a StateMachineState to the Behavior.
     *
     * @param StateMachineState $state            
     */
    public function addState($state)
    {
        $this->states[$state->getStateId()] = $state;
    }

    /**
     * Returns the states of the state machine.
     *
     * @return \exface\Core\CommonLogic\UxonObject
     */
    public function getUxonStates()
    {
        return $this->uxon_states;
    }

    /**
     * Returns an array of buttons belonging to the StateMachineState with the
     * passed state id.
     *
     * @param integer|string $state_id            
     * @return UxonObject[]
     */
    public function getStateButtons($state_id)
    {
        if ($this->isDisabled() || ! $this->getStates())
            return [];
        $smstate = $this->getState($state_id);
        if (! $smstate) {
            $smstate = $this->getDefaultState();
        }
        return $smstate instanceof StateMachineState ? $smstate->getButtons() : [];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('state_attribute_alias', $this->getStateAttributeAlias());
        $uxon->setProperty('default_state', $this->getDefaultStateId());
        $uxon->setProperty('states', $this->getStates());
        return $uxon;
    }

    /**
     * This method is called when a widget belonging to an object with this event
     * attached is being prefilled.
     * It is checked if this widget belongs to a dis-
     * abled attribute. If so the widget gets also disabled.
     *
     * @param WidgetEvent $event            
     */
    public function setWidgetStates(WidgetEvent $event)
    {
        if ($this->isDisabled())
            return;
        if (! $this->getStateAttributeAlias() || ! $this->getStates())
            return;
        
        $widget = $event->getWidget();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $widget->getMetaObject()->is($this->getObject()))
            return;
        
        if (! ($prefill_data = $widget->getPrefillData()) || ! ($prefill_data->getUidColumn()) || ! ($state_column = $prefill_data->getColumnValues($this->getStateAttributeAlias())) || ! ($current_state = $state_column[0])) {
            $current_state = $this->getDefaultStateId();
        }
        
        // Throw an error if the current state is not in the state machine definition!
        if ($current_state && ! $this->getState($current_state)) {
            throw new BehaviorRuntimeError($this->getObject(), 'Cannot disable widget of uneditable attributes for state "' . $current_state . '": State not found in the the state machine behavior definition!', '6UMF9UL');
        }
        
        if (($widget instanceof iShowSingleAttribute) && ($disabled_attributes = $this->getState($current_state)->getDisabledAttributesAliases()) && in_array($widget->getAttributeAlias(), $disabled_attributes)) {
            // set_readonly() statt set_disabled(), dadurch werden die deaktivierten
            // Widgets nicht gespeichert. Behebt einen Fehler, der dadurch ausgeloest
            // wurde, dass ein deaktiviertes Widget durch einen Link geaendert wurde,
            // und sich der Wert dadurch vom Wert in der DB unterschied ->
            // StateMachineUpdateException
            $widget->setReadonly(true);
        }
    }

    /**
     * This method is called when an object with this event attached is being updated.
     * Here it is checked the object changes the state and if so if the state-transition
     * is allowed. It is also checked if attributes, which are disabled at the current
     * state are changed. If a disallowed behavior is detected an error is thrown.
     *
     * @param DataSheetEvent $event            
     * @throws StateMachineUpdateException
     */
    public function checkForConflictsOnUpdate(DataSheetEvent $event)
    {
        if ($this->isDisabled())
            return;
        if (! $this->getStateAttributeAlias() || ! $this->getStates())
            return;
        
        $data_sheet = $event->getDataSheet();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->is($this->getObject()))
            return;
        
        // Read the unchanged object from the database
        $check_sheet = DataSheetFactory::createFromObject($this->getObject());
        foreach ($this->getObject()->getAttributes() as $attr) {
            $check_sheet->getColumns()->addFromAttribute($attr);
        }
        $check_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
        $check_sheet->dataRead();
        $check_column = $check_sheet->getColumns()->getByAttribute($this->getStateAttribute());
        $check_nr = count($check_column->getValues());
        
        // Check if the state column is present in the sheet, if so get the old value and check
        // if the transition is allowed, throw an error if not
        if ($updated_column = $data_sheet->getColumns()->getByAttribute($this->getStateAttribute())) {
            $update_nr = count($updated_column->getValues());
            
            if ($check_nr == $update_nr) {
                // beim Bearbeiten eines einzelnen Objektes ueber einfaches Bearbeiten, Massenupdate in Tabelle, Massenupdate
                // ueber Knopf $check_nr == 1, $update_nr == 1
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate in Tabelle $check_nr == $update_nr > 1
                foreach ($updated_column->getValues() as $row_nr => $updated_val) {
                    $check_val = $check_column->getCellValue($check_sheet->getUidColumn()
                        ->findRowByValue($data_sheet->getUidColumn()
                        ->getCellValue($row_nr)));
                    $allowed_transitions = $this->getState($check_val)->getTransitions();
                    if (! in_array($updated_val, $allowed_transitions)) {
                        $data_sheet->dataMarkInvalid();
                        throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": state transition from ' . $check_val . ' to ' . $updated_val . ' is not allowed!', '6VC040N');
                    }
                }
            } else if ($check_nr > 1 && $update_nr == 1) {
                // beim Bearbeiten mehrerer Objekte ueber Massenupdate ueber Knopf, Massenupdate ueber Knopf mit Filtern
                // $check_nr > 1, $update_nr == 1
                $updated_val = $updated_column->getValues()[0];
                foreach ($check_column->getValues() as $row_nr => $check_val) {
                    $allowed_transitions = $this->getState($check_val)->getTransitions();
                    if (! in_array($updated_val, $allowed_transitions)) {
                        $data_sheet->dataMarkInvalid();
                        throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": state transition from ' . $check_val . ' to ' . $updated_val . ' is not allowed!', '6VC040N');
                    }
                }
            }
        }
        
        // Check all the updated attributes for disabled attributes, if a disabled attribute
        // is changed throw an error
        foreach ($data_sheet->getRows() as $updated_row_nr => $updated_row) {
            $check_row_nr = $check_sheet->getUidColumn()->findRowByValue($data_sheet->getUidColumn()
                ->getCellValue($updated_row_nr));
            $check_state_val = $check_column->getCellValue($check_row_nr);
            $disabled_attributes = $this->getState($check_state_val)->getDisabledAttributesAliases();
            foreach ($updated_row as $attribute_alias => $updated_val) {
                if (in_array($attribute_alias, $disabled_attributes)) {
                    $check_val = $check_sheet->getCellValue($attribute_alias, $check_row_nr);
                    if ($updated_val != $check_val) {
                        $data_sheet->dataMarkInvalid();
                        throw new StateMachineUpdateException($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": attribute ' . $attribute_alias . ' is disabled in the current state (' . $check_state_val . ')!', '6VC07QH');
                    }
                }
            }
        }
    }

    /**
     * Sets color map for use in for instance ProgressBar formula.
     *
     * @param array $progress_bar_color_map            
     */
    public function setProgressBarColorMap($progress_bar_color_map)
    {
        $uxonColorMap = UxonObject::fromAnything($progress_bar_color_map);
        if ($uxonColorMap instanceof UxonObject) {
            $colorMap = array();
            foreach ($uxonColorMap as $progressBarValue => $color) {
                $colorMap[$progressBarValue] = $color;
            }
            $this->progress_bar_color_map = $colorMap;
        } elseif (is_array($progress_bar_color_map)) {
            $this->progress_bar_color_map = $progress_bar_color_map;
        } else {
            throw new BehaviorConfigurationError($this->getObject(), 'Can not set progress_bar_color_map for "' . $this->getObject()->getAliasWithNamespace() . '": the argument passed to set_progress_bar_color_map() is neither an UxonObject nor an array!', '6TG2ZFI');
        }
    }

    /**
     * Returns color map for use in for instance ProgressBar formula.
     *
     * @return array
     */
    public function getProgressBarColorMap()
    {
        return $this->progress_bar_color_map;
    }
}

?>