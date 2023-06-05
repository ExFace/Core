<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\Exceptions\Behaviors\StateMachineTransitionError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Behaviors\BehaviorRuntimeError;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\CommonLogic\Model\Behaviors\StateMachineState;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Events\Widget\OnPrefillEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\Widgets\ProgressBar;
use exface\Core\Events\Model\OnMetaAttributeModelValidatedEvent;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Exceptions\Behaviors\DataSheetDeleteForbiddenError;
use exface\Core\Events\Model\OnBehaviorModelValidatedEvent;
use exface\Core\CommonLogic\WidgetDimension;
use exface\Core\Factories\WidgetDimensionFactory;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\BehaviorFactory;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;

/**
 * Makes it possible to model states of an object and transitions between them.
 * 
 * An object with this behavior automatically acts as a state machine. One of the attributes
 * is specified as the current state. All possible states are defined within the behavior's
 * configuration as well as all allowed transitions and state-specific actions. You can also
 * make certain attributes disabled in selected states.
 * 
 * The behavior makes sure the state of the object cannot be changed if there is no transition
 * allowed between the old and the new state.
 * 
 * There are also special widgets, that help organize state management:
 * 
 * - The `StateInputSelect` produces an InputSelect with all states, that can be reached from the
 * current state of the object
 * - The `StateMenuButton` automatically generates a menu with all state-specific actions
 * 
 * By default, this behavior will override the data type and the default widgets of the state
 * attribute according to the state machine configuration: Editor widgets will automatically
 * be defined as `StateInputSelect`, while display-widgets will be displayed as a progress bar
 * for numeric states. These features are controlled by the behavior properties `override_attribute_data_type`,
 * `override_attribute_display_widget`, `override_attribute_editor_widget` and `show_state_as_progress_bar`.
 *
 * @author SFL
 */
class StateMachineBehavior extends AbstractBehavior
{

    private $state_attribute_alias = null;

    private $default_state = null;

    private $uxon_states = null;

    private $states = null;
    
    private $stateIndex = [];
    
    private $overrideAttributeEditorWidget = true;
    
    private $overrideAttributeDisplayWidget = true;
    
    private $overrideAttributeDataType = true;
    
    private $hideStateIds = false;
    
    private $showStateAsProgressBar = null;
    
    private $hasNumericIds = true;
    
    private $behaviors = null;
    
    private $displayWidgetWidth = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::registerEventListeners()
     */
    protected function registerEventListeners() : BehaviorInterface
    {
        $prio = $this->getPriority();
        $this->getWorkbench()->eventManager()->addListener(OnPrefillEvent::getEventName(), [$this, 'setWidgetStates'], $prio);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'checkForConflictsOnUpdate'], $prio);
        $this->getWorkbench()->eventManager()->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'checkForConflictsOnDelete'], $prio);
        
        if ($this->getOverrideAttributeDataType() === true) {
            $this->overrideAttributeDataType();
        }
        
        if ($this->getOverrideAttributeEditorWidget() === true) {
            $this->overrideAttributeEditorWidget();
        }
        
        if ($this->getOverrideAttributeDisplayWidget() === true) {
            $this->overrideAttributeDisplayWidget();
        }
        
        $this->registerNotifications();
        foreach ($this->behaviors as $behavior) {
            if ($behavior->is)
                $behavior->enable();
        }
        
        $this->getWorkbench()->eventManager()->addListener(OnBehaviorModelValidatedEvent::getEventName(), [$this, 'onModelValidatedAddDiagram'], $prio);
        
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::unregisterEventListeners()
     */
    protected function unregisterEventListeners() : BehaviorInterface
    {
        $this->getWorkbench()->eventManager()->removeListener(OnPrefillEvent::getEventName(), [$this, 'setWidgetStates']);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'checkForConflictsOnUpdate']);
        $this->getWorkbench()->eventManager()->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'checkForConflictsOnDelete']);
        
        foreach ($this->behaviors as $behavior) {
            $behavior->disable();
        }
        
        $this->getWorkbench()->eventManager()->removeListener(OnBehaviorModelValidatedEvent::getEventName(), [$this, 'onModelValidatedAddDiagram']);
        
        return $this;
    }
    
    protected function overrideAttributeDataType() : StateMachineBehavior
    {
        $attr = $this->getStateAttribute();
        $type = $attr->getDataType();
        
        $enumVals = [];
        foreach ($this->getStates() as $state) {
            $enumVals[$state->getStateId()] = $state->getName(! $this->getHideStateIds());
        }
        // Set show_values to FALSE in the UXON because they are already included in the labels
        $configUxon = new UxonObject(['values' => $enumVals, 'show_values' => false]);
        
        $attr->setCustomDataTypeUxon($configUxon);
        
        $enumType = null;
        if (! ($type instanceof EnumDataTypeInterface)) {
            if ($type instanceof NumberDataType) {
                $enumType = '0x11e7c39621725c1e8001e4b318306b9a';
            } else {
                $enumType = '0x11e7c3960c3586c38001e4b318306b9a';
            }
            $attr->setDataType($enumType);
        }
        
        $this->getWorkbench()->eventManager()->addListener(OnMetaAttributeModelValidatedEvent::getEventName(), function(OnMetaAttributeModelValidatedEvent $event) use ($enumType, $configUxon) {
            if ($event->getAttribute()->isExactly($this->getStateAttribute()) === false) {
                return;
            }
            
            $widget = $event->getMessageList()->getParent();
            $foundTypeSelector = false;
            $foundTypeConfig = false;
            foreach ($widget->getChildrenRecursive() as $child) {
                if (($child instanceof iShowSingleAttribute) && $child->getAttributeAlias() === 'DATATYPE') {
                    if ($enumType !== null) {
                        // Change the value only if the saved type does not fit (= the custom $enumType is set)
                        $child->setValue($enumType);
                        $child->setValueText('');
                    }
                    $child->setDisabled(true);
                    $child->setHint($this->translate('BEHAVIOR.ALL.PROPERTY_HINT_AUTOGENERATED_BY', ['%behavior%' => $this->getAlias()]) . "\n" . $child->getHint());
                    $foundTypeSelector = true;
                }
                
                if (($child instanceof iShowSingleAttribute) && $child->getAttributeAlias() === 'CUSTOM_DATA_TYPE') {
                    $child->setValue($configUxon->toJson());
                    $child->setDisabled(true);
                    $child->setHint($this->translate('BEHAVIOR.ALL.PROPERTY_HINT_AUTOGENERATED_BY', ['%behavior%' => $this->getAlias()]) . "\n" . $child->getHint());
                    $foundTypeConfig = true;
                }
                
                if ($foundTypeConfig === true && $foundTypeSelector === true) {
                    break;
                }
            }
        });
        
        return $this;
    }
    
    /**
     * 
     * @return StateMachineBehavior
     */
    protected function overrideAttributeEditorWidget() : StateMachineBehavior
    {
        $uxon = new UxonObject([
            'widget_type' => 'StateInputSelect'
        ]);
        $this->getStateAttribute()->setDefaultEditorUxon($uxon);
        
        $this->getWorkbench()->eventManager()->addListener(OnMetaAttributeModelValidatedEvent::getEventName(), function(OnMetaAttributeModelValidatedEvent $event) use ($uxon) {
            if ($event->getAttribute()->isExactly($this->getStateAttribute()) === false) {
                return;
            }
            
            $widget = $event->getMessageList()->getParent();
            foreach ($widget->getChildrenRecursive() as $child) {
                if (($child instanceof iShowSingleAttribute) && $child->getAttributeAlias() === 'DEFAULT_EDITOR_UXON') {
                    $child->setValue($uxon->toJson());
                    $child->setDisabled(true);
                    $child->setHint($this->translate('BEHAVIOR.ALL.PROPERTY_HINT_AUTOGENERATED_BY', ['%behavior%' => $this->getAlias()]) . "\n" . $child->getHint());
                    break;
                }
            }
        });
        
        return $this;
    }
    
    /**
     * 
     * @return StateMachineBehavior
     */
    protected function overrideAttributeDisplayWidget() : StateMachineBehavior
    {
        if ($this->getShowStateAsProgressBar() === true) {
            $min = 0;
            $max = 0;
            $texts = [];
            $colorMap = [];
            
            foreach ($this->getStates() as $state) {
                $id = $state->getStateId();
                if ($id < $min) {
                    $min = $id;
                }
                if ($id > $max) {
                    $max = $id;
                }
                $texts[$id] = $state->getName(! $this->getHideStateIds());
                if ($state->getColor() !== null) {
                    $colorMap[$id] = $state->getColor();
                }
            }
            
            if (count($colorMap) < count($texts)) {
                $missingKeys = array_diff(array_keys($texts), array_keys($colorMap));
                foreach ($missingKeys as $key) {
                    $colorMap[$key] = $this->getDefaultColor($key, $min, $max);
                }
            }
            
            $uxon = new UxonObject([
                'widget_type' => 'ProgressBar',
                'align' => EXF_ALIGN_DEFAULT,
                'min' => $min,
                'max' => $max,
                'text_scale' => new UxonObject($texts),
                'color_scale' => new UxonObject($colorMap)
            ]);
            if ($this->getDisplayWidgetWidth()) {
                $uxon->setProperty('width', $this->getDisplayWidgetWidth()->getValue());
            }
            $this->getStateAttribute()->setDefaultDisplayUxon($uxon);
        }
        
        return $this;
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
            throw new BehaviorConfigurationError($this, 'Cannot initialize StateMachineBehavior for "' . $this->getObject()->getAliasWithNamespace() . '": state_attribute_alias not set in behavior configuration!', '6TG2ZFI');
        }
        return $this->state_attribute_alias;
    }

    /**
     * Defines the attribute alias, that holds the state id.
     *
     * @uxon-property state_attribute_alias
     * @uxon-type metamodel:attribute
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
     * @return \exface\Core\Interfaces\Model\MetaAttributeInterface
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
     * Defines the default state id, which is to be used if no object state can be determined.
     * 
     * The default state is used, for example, to determine possible values for the StateMenuButton.
     * 
     * If not specified explicitly, the first state in the list will be assumed to be the
     * default one.
     *
     * @uxon-property default_state
     * @uxon-type string
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
            throw new BehaviorConfigurationError($this, 'Can not set default state for "' . $this->getObject()->getAliasWithNamespace() . '": the argument passed to setDefaultState() is neither a StateMachineState nor an integer nor a string!', '6TG2ZFI');
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
                throw new BehaviorConfigurationError($this, 'The default state cannot be determined for "' . $this->getObject()->getAliasWithNamespace() . '": neither state definitions nor a default state are set!', '6TG2ZFI');
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
     * Returns an array with the ids of all states
     * 
     * @return string[]
     */
    public function getStateIds() : array
    {
        return array_keys($this->getStates());
    }

    /**
     * Defines the states of the state machine.
     *
     * The states are set by a JSON object or array with state ids for keys and an objects describing the state for values.
     *
     * Example:
     * 
     * ```
     *  "states": {
     *      "10": {
     *          "name": "Received",
     *          "transitions": [
     *              10: "",
     *              20: "my.App.ComplaintApprove",
     *              90: "my.App.ComplaintCancel"
     *          ],
     *          "disabled_attributes_aliases": [
     *              "COMPLAINT_NO"
     *          ]
     *      },
     *      "20": {
     *          "name": "Approved",
     *          "transitions": ...,
     *          ...
     *      }
     *  }
     *  
     * ```
     *
     * @uxon-property states
     * @uxon-type \exface\Core\CommonLogic\Model\Behaviors\StateMachineState[]
     * @uxon-template {"": {"name": ""}}
     *
     * @param UxonObject|StateMachineState[] $value            
     * @throws BehaviorConfigurationError
     * @return \exface\Core\Behaviors\StateMachineBehavior
     */
    public function setStates($value)
    {
        if ($value instanceof UxonObject) { 
            $this->uxon_states = $value;
            $this->states = [];
            foreach ($value as $state => $uxon_smstate) {
                if (is_numeric($state) === false) {
                    $this->hasNumericIds = false;
                }
                $this->addState(new StateMachineState($this, $state, $uxon_smstate));
            }
        } elseif (is_array($value)) {
            $this->states = $value;
        } else {
            throw new BehaviorConfigurationError($this, 'Can not set states for "' . $this->getObject()->getAliasWithNamespace() . '": the argument passed to setStates() is neither an UxonObject nor an array!', '6TG2ZFI');
        }
        
        $this->stateIndex = array_keys($this->states);
        
        return $this;
    }
    
    /**
     * Returns TRUE if all state ids are numeric and FALSE otherwise. 
     * 
     * @return bool
     */
    protected function hasNumeriIds() : bool
    {
        return $this->hasNumericIds;
    }

    /**
     * Returns the StateMachineState object belonging to the passed state id.
     *
     * @param integer|string $state_id            
     * @return StateMachineState
     */
    public function getState($state_id) : StateMachineState
    {
        $state = $this->states[$state_id];
        if ($state === null) {
            throw new BehaviorRuntimeError($this, 'Unknown state machine state "' . $state_id . '" for object ' . $this->getObject()->__toString() . '!');
        }
        return $state;
    }
    
    /**
     * 
     * @param mixed $state_id
     * @return bool
     */
    public function hasState($state_id) : bool
    {
        return $this->states[$state_id] !== null;
    }
    
    /**
     * 
     * @param StateMachineState|string $state
     * @return int
     */
    public function getStateIndex($state) : int
    {
        $stateId = $state instanceof StateMachineState ? $state->getStateId() : $state;
        return array_search($stateId, $this->stateIndex);
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
     * @see \exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('state_attribute_alias', $this->getStateAttributeAlias());
        $uxon->setProperty('default_state', $this->getDefaultStateId());
        $uxon->setProperty('states', $this->getStates());
        $uxon->setProperty('override_attribute_data_type', $this->getOverrideAttributeDataType());
        $uxon->setProperty('override_attribute_display_widget', $this->getOverrideAttributeDisplayWidget());
        $uxon->setProperty('override_attribute_editor_widget', $this->getOverrideAttributeEditorWidget());
        $uxon->setProperty('show_state_as_progress_bar', $this->getShowStateAsProgressBar());
        return $uxon;
    }

    /**
     * This method is called when a widget belonging to an object with this event
     * attached is being prefilled.
     * It is checked if this widget belongs to a dis-
     * abled attribute. If so the widget gets also disabled.
     *
     * @param OnPrefillEvent $event            
     */
    public function setWidgetStates(OnPrefillEvent $event)
    {
        if ($this->isDisabled())
            return;
        if (! $this->getStateAttributeAlias() || ! $this->getStates())
            return;
        
        $widget = $event->getWidget();
        $thisObj = $this->getObject();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $widget->getMetaObject()->isExactly($thisObj)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        if (! ($prefill_data = $widget->getPrefillData()) || ! $prefill_data->getMetaObject()->isExactly($thisObj) || ! ($prefill_data->getUidColumn()) || ! ($state_column = $prefill_data->getColumnValues($this->getStateAttributeAlias())) || ! ($current_state = $state_column[0])) {
            $current_state = $this->getDefaultStateId();
        }
        
        // Throw an error if the current state is not in the state machine definition!
        if ($current_state && ! $this->getState($current_state)) {
            throw new BehaviorRuntimeError($this, 'Cannot disable widget of uneditable attributes for state "' . $current_state . '": State not found in the the state machine behavior definition!', '6UMF9UL');
        }
        
        $state = $this->getState($current_state);
        
        // Disable attribute editors if editing is disabled completely or for the specific attribute
        if ($widget instanceof iShowSingleAttribute) {
            if ($widget->isBoundToAttribute() && $state->isAttributeDisabled($widget->getAttribute()) === true) {
                // set_readonly() statt set_disabled(), dadurch werden die deaktivierten
                // Widgets nicht gespeichert. Behebt einen Fehler, der dadurch ausgeloest
                // wurde, dass ein deaktiviertes Widget durch einen Link geaendert wurde,
                // und sich der Wert dadurch vom Wert in der DB unterschied ->
                // StateMachineTransitionError
                if ($widget instanceof iTakeInput) {
                    $widget->setReadonly(true);
                } else {
                    $widget->setDisabled(true);
                }
            }
        }
        
        // Disable buttons saving or deleting data if the respecitve operations are disabled in the current state.
        if ($widget instanceof iHaveButtons) {
            foreach ($widget->getButtons() as $btn) {
                if (! $btn->getMetaObject()->isExactly($thisObj)) {
                    continue;
                }
                
                if ($btn->hasAction() && $btn->getAction()->getMetaObject()->isExactly($thisObj)) {
                    if ($state->isActionDisabled($btn->getAction()) === true) {
                        $btn->setDisabled(true);
                    }
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }
    
    /**
     * 
     * @param OnBeforeDeleteDataEvent $event
     * @throws BehaviorRuntimeError
     * @throws DataSheetDeleteForbiddenError
     * @return void
     */
    public function checkForConflictsOnDelete(OnBeforeDeleteDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if (! $this->getStateAttributeAlias() || empty($this->getStates())) {
            return;
        }
        
        $data_sheet = $event->getDataSheet();
        $thisObj = $this->getObject();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($thisObj)) {
            return;
        }
        
        $states = $this->getStatesWithDisabledDelete();
        if (empty($states)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        $stateCol = $data_sheet->getColumns()->getByAttribute($this->getStateAttribute());
        if (! $stateCol) {
            throw new BehaviorRuntimeError($this, 'Cannot check if DELETE operation allowed in current state of ' . $thisObj->__toString() . ': no state value found in input data of action!');
        }
        
        foreach ($states as $state) {
            $stateId = $state->getStateId();
            foreach ($stateCol->getValues(false) as $stateVal) {
                if ($stateVal == $stateId) {
                    $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
                    throw (new DataSheetDeleteForbiddenError($data_sheet, $translator->translate('BEHAVIOR.STATEMACHINEBEHAVIOR.DELETE_FORBIDDEN_ERROR', ['%object%' => $thisObj->getName(), '%state%' => $state->getName()])))->setUseExceptionMessageAsTitle(true);
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }

    /**
     * This method is called when an object with this event attached is being updated.
     * Here it is checked the object changes the state and if so if the state-transition
     * is allowed. It is also checked if attributes, which are disabled at the current
     * state are changed. If a disallowed behavior is detected an error is thrown.
     *
     * @param OnBeforeUpdateDataEvent $event            
     * @throws StateMachineTransitionError
     */
    public function checkForConflictsOnUpdate(OnBeforeUpdateDataEvent $event)
    {
        if ($this->isDisabled()) {
            return;
        }
        
        if (! $this->getStateAttributeAlias() || empty($this->getStates())) {
            return;
        }
        
        $data_sheet = $event->getDataSheet();
        $thisObj = $this->getObject();
        
        // Do not do anything, if the base object of the widget is not the object with the behavior and is not
        // extended from it.
        if (! $data_sheet->getMetaObject()->isExactly($thisObj)) {
            return;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeBehaviorAppliedEvent($this, $event));
        
        if ($this->hasTransitionRestrictions()) {
            
            // Read the unchanged object from the database
            $check_sheet = DataSheetFactory::createFromObject($thisObj);
            $check_column = $check_sheet->getColumns()->addFromAttribute($this->getStateAttribute());
            if ($this->getObject()->hasUidAttribute()) {
                $check_sheet->getColumns()->addFromUidAttribute();
            }
            if ($data_sheet->hasUidColumn(true)) {
                $check_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
            } else {
                $check_sheet->setFilters($data_sheet->getFilters()->copy());
            }
            
            $check_sheet->dataRead();
            $check_cnt = count($check_column->getValues());
            
            // Check if the state column is present in the sheet, if so get the old value and check
            // if the transition is allowed, throw an error if not
            if ($updated_column = $data_sheet->getColumns()->getByAttribute($this->getStateAttribute())) {
                $update_cnt = count($updated_column->getValues());
                $error = null;
                
                if ($check_cnt == $update_cnt) {
                    // beim Bearbeiten eines einzelnen Objektes ueber einfaches Bearbeiten, Massenupdate in Tabelle, Massenupdate
                    // ueber Knopf $check_nr == 1, $update_nr == 1
                    // beim Bearbeiten mehrerer Objekte ueber Massenupdate in Tabelle $check_nr == $update_nr > 1
                    foreach ($updated_column->getValues() as $row_nr => $updated_val) {
                        $check_val = $check_column->getCellValue($check_sheet->getUidColumn()->findRowByValue($data_sheet->getUidColumn()->getCellValue($row_nr)));
                        $from_state = $this->getState($check_val);
                        $to_state = $this->getState($updated_val);
                        if ($from_state->isTransitionAllowed($to_state) === false) {
                            $error = 'state transition from ' . $from_state->getName() . ' (' . $check_val . ') to ' . $to_state->getName() . ' (' . $updated_val . ') is not allowed';
                            break;
                        }
                        foreach ($from_state->getDisabledAttributesAliases() as $disabledAttrAlias) {
                            if ($event->willChangeAttribute($thisObj->getAttribute($disabledAttrAlias))) {
                                $error = 'no changes to attribute "' . $thisObj->getAttribute($disabledAttrAlias)->getName() . '" allowed in state ' . $from_state->getName() . ' (' . $check_val . ')';
                                break 2;
                            }
                        }
                    }
                } else if ($check_cnt > 1 && $update_cnt == 1) {
                    // beim Bearbeiten mehrerer Objekte ueber Massenupdate ueber Knopf, Massenupdate ueber Knopf mit Filtern
                    // $check_nr > 1, $update_nr == 1
                    $updated_val = $updated_column->getValues()[0];
                    $to_state = $this->getState($updated_val);
                    foreach ($check_column->getValues() as $row_nr => $check_val) {
                        $from_state = $this->getState($check_val);
                        if ($from_state->isTransitionAllowed($to_state) === false) {
                            $error = 'state transition from ' . $from_state->getName() . ' (' . $check_val . ') to ' . $to_state->getName() . ' (' . $updated_val . ') is not allowed';    
                            break;
                        }
                    }
                    foreach ($from_state->getDisabledAttributesAliases() as $disabledAttrAlias) {
                        if ($event->willChangeAttribute($thisObj->getAttribute($disabledAttrAlias))) {
                            $error = 'no changes to attribute "' . $thisObj->getAttribute($disabledAttrAlias)->getName() . '" allowed in state ' . $from_state->getName() . ' (' . $check_val . ')';
                            break;
                        }
                    }
                }
                
                if ($error !== null) {
                    $data_sheet->dataMarkInvalid();
                    throw new StateMachineTransitionError($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": ' . $error . '!', '6VC040N');
                }
            }
            
        }
        
        // Check all the updated attributes for disabled attributes, if a disabled attribute
        // is changed throw an error
        if ($this->hasDisabledAttributes()) {
            foreach ($data_sheet->getRows() as $updated_row_nr => $updated_row) {
                $check_row_nr = $check_sheet->getUidColumn()->findRowByValue($data_sheet->getUidColumn()->getCellValue($updated_row_nr));
                $check_state_val = $check_column->getCellValue($check_row_nr);
                $state = $this->getState($check_state_val);            
                foreach ($updated_row as $colum_name => $updated_val) {
                    $col = $data_sheet->getColumns()->get($colum_name);
                    if ($col->isAttribute() === true && $state->isAttributeDisabled($col->getAttribute()) === true) {
                        $check_val = $col->getCellValue($check_row_nr);
                        if ($updated_val != $check_val) {
                            $data_sheet->dataMarkInvalid();
                            throw new StateMachineTransitionError($data_sheet, 'Cannot update data in data sheet with "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": attribute ' . $col->getAttribute()->__toString() . ' is disabled in the current state "' . $state->getName() . '"!', '6VC07QH');
                        }
                    }
                }
            }
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnBehaviorAppliedEvent($this, $event));
        return;
    }
    
    /**
     *
     * @return bool
     */
    public function getOverrideAttributeDisplayWidget() : bool
    {
        return $this->overrideAttributeDisplayWidget;
    }
    
    /**
     * The state attribute will get a preconfigured default display widget - e.g. ProgressBar - if TRUE (default).
     * 
     * @uxon-property override_attribute_display_widget
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool|string $value
     * @return StateMachineBehavior
     */
    public function setOverrideAttributeDisplayWidget($value) : StateMachineBehavior
    {
        $this->overrideAttributeDisplayWidget = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * Sets the width of the widget.
     * Set to `1` for default widget width in a facade or `max` for maximum width possible.
     *
     * The width can be specified either in
     * - facade-specific relative units (e.g. `width: 2` makes the widget twice as wide
     * as the default width of a widget in the current facade)
     * - percent (e.g. `width: 50%` will make the widget take up half the available space)
     * - any other facade-compatible units (e.g. `width: 200px` will work in CSS-based facades)
     *
     * @uxon-property display_widget_width
     * @uxon-type string
     **/ 
    public function setDisplayWidgetWidth($value)
    {
        $exface = $this->getWorkbench();
        $this->displayWidgetWidth = WidgetDimensionFactory::createFromAnything($exface, $value);
        return $this;
    }
    
    /**
     * 
     * @return WidgetDimension|NULL
     */
    protected function getDisplayWidgetWidth() : ?WidgetDimension
    {
        return $this->displayWidgetWidth;
    }
    
    /**
     *
     * @return bool
     */
    public function getOverrideAttributeEditorWidget() : bool
    {
        return $this->overrideAttributeEditorWidget;
    }
    
    /**
     *  The state attribute will get the StateInputSelect as default editor widget if set to TRUE (default).
     * 
     * @uxon-property override_attribute_editor_widget
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool|string $value
     * @return StateMachineBehavior
     */
    public function setOverrideAttributeEditorWidget($value) : StateMachineBehavior
    {
        $this->overrideAttributeEditorWidget = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getOverrideAttributeDataType() : bool
    {
        return $this->overrideAttributeDataType;
    }
    
    /**
     * The state attribute will get an autogenerated enumeration data type if TRUE (default).
     * 
     * @uxon-property override_attribute_data_type
     * @uxon-type boolean
     * @uxon-default true
     *
     * @param bool|string $value
     * @return StateMachineBehavior
     */
    public function setOverrideAttributeDataType($value) : StateMachineBehavior
    {
        $this->overrideAttributeDataType = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getHideStateIds() : bool
    {
        return $this->hideStateIds;
    }
    
    /**
     * Set to TRUE to only show state names in auto-generated widgets - by default ids will be shown too.
     * 
     * @uxon-property hide_state_ids
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool|string $value
     * @return StateMachineBehavior
     */
    public function setHideStateIds($value) : StateMachineBehavior
    {
        $this->hideStateIds = BooleanDataType::cast($value);
        return $this;
    }  
    
    /**
     * Returns TRUE if the state should be shown as a progress bar.
     * 
     * If not set explicitly via UXON or setShowStateAsProgressBar(), a progress bar will be
     * automatically enabled if all state ids are numeric.
     * 
     * @return bool
     */
    public function getShowStateAsProgressBar() : bool
    {
        return $this->showStateAsProgressBar ?? $this->hasNumeriIds();
    }
    
    /**
     * If TRUE (default), the state attribute will automatically get the ProgressBar as default display widget.
     * 
     * @uxon-property show_state_as_progress_bar
     * @uxon-type boolean
     * 
     * @param bool|string $value
     * @return StateMachineBehavior
     */
    public function setShowStateAsProgressBar($value) : StateMachineBehavior
    {
        $this->showStateAsProgressBar = BooleanDataType::cast($value);
        return $this;
    }
    
    /**
     * Returns the default color code for a state (e.g. if it has no color set explicitly)
     * 
     * @param string|number $state
     * @param float $min
     * @param float $max
     * 
     * @return string
     */
    protected function getDefaultColor($state, float $min, float $max) : string
    {
        if (! is_numeric($state)) {
            return '';
        }
        
        $colorMap = ProgressBar::getColorScaleDefault($min, $max);
        return ProgressBar::findColor($state, $colorMap);
    }    
    
    protected function translate(string $messageId, array $placeholderValues = null, float $pluralNumber = null) : string
    {
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate($messageId, $placeholderValues, $pluralNumber);
    }
    
    /**
     * 
     * @return StateMachineState[]
     */
    protected function getStatesWithDisabledDelete() : array
    {
        $arr = [];
        foreach ($this->getStates() as $state) {
            if ($state->getDisableDelete()) {
                $arr[] = $state;
            }
        }
        return $arr;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasTransitionRestrictions() : bool
    {
        foreach ($this->getStates() as $state) {
            if ($state->hasTransitionRestrictions()) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * @return bool
     */
    protected function hasDisabledAttributes() : bool
    {
        foreach ($this->getStates() as $state) {
            if ($state->hasDisabledAttributes()) {
                return true;
            }
        }
        return false;
    }
    
    protected function registerNotifications() : StateMachineBehavior
    {
        // Only register behaviors once!
        if ($this->behaviors !== null) {
            return $this;
        } else {
            $this->behaviors = [];
        }
        
        foreach ($this->getStates() as $state) {
            if (null !== $notifications = $state->getNotificationsUxon()) {
                $uxon = new UxonObject([
                    "notify_on_event" => $state->isStartState() ? OnCreateDataEvent::getEventName() : OnUpdateDataEvent::getEventName(),
                    "notify_if_attributes_change" => [$this->getStateAttributeAlias()],
                    "notify_if_data_matches_conditions" => [
                        "operator" => EXF_LOGICAL_AND,
                        "conditions" => [
                            [
                                "expression" => $this->getStateAttributeAlias(),
                                "comparator" => ComparatorDataType::EQUALS,
                                "value" => $state->getStateId()
                            ]
                        ]
                    ],
                    'notifications' => $notifications
                ]);
                $behavior = BehaviorFactory::createFromUxon($this->getObject(), NotifyingBehavior::class, $uxon, $this->getApp()->getSelector());
                $this->getObject()->getBehaviors()->add($behavior);
                $this->behaviors[] = $behavior;
            }
        }
        return $this;
    }

    /**
     * 
     * @param OnBehaviorModelValidatedEvent $event
     * @return void
     */
    public function onModelValidatedAddDiagram(OnBehaviorModelValidatedEvent $event)
    {
        if ($event->getBehavior() !== $this) {
            return;
        }
        
        $widget = $event->getMessageList()->getParent();
        $widget->addButton($widget->createButton(new UxonObject([
            'caption' => 'Diagram',
            'close_dialog' => false,
            'action' => [
                'alias' => 'exface.Core.ShowDialog',
                'dialog' => [
                    'lazy_loading' => false,
                    'maximized' => true,
                    'widgets' => [
                        [
                            'widget_type' => 'Markdown',
                            'width' => '100%',
                            'height' => '100%',
                            'value' => $this->buildMermaidDiagram(),
                            'renderMermaidDiagrams' => true
                        ]
                    ]
                ]
            ]
        ])));
        return;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildMermaidDiagram() : string
    {
        $mm = '';
        foreach ($this->getStates() as $state) {
            $stateFlags = '';
            if ($state->getDisableDelete() || $state->getDisableEditing()) {
                $stateFlags .= ' #128274;';
            }
            $stateProps = [
                'State ID - ' . $state->getStateId(),
                'State color - ' . $state->getColor(),
                'State icon - ' . $state->getIcon(),
                ($state->getDisableDelete() ? '#9745;' : '#9744;') . ' disable delete',
                ($state->getDisableDelete() ? '#9745;' : '#9744;') . ' disable edit'
            ];
            
            $stateProps = implode('#013;', $stateProps);
            $stateProps = str_replace(':', '#58;', $stateProps);
            
            $stateDetails = '';
            if ($state->getDescription() !== null) {
                $stateDetails .= '<i>' . str_replace(';', '#59;', wordwrap($state->getDescription(), 50, '<br>')) . '</i><br>';
            }
            if (null !== $notificationsUxon = $state->getNotificationsUxon()) {
                foreach ($notificationsUxon->getPropertiesAll() as $notificationUxon) {
                    $stateDetails .= "<br><b>#9993;</b> " . $notificationUxon->getProperty('template') ?? $notificationUxon->getProperty('channel');
                    foreach (($notificationUxon->getProperty('recipient_roles')) ?? [] as $recipient) {
                        $stateDetails .= "<br>- " . str_replace(';', '#59;', $recipient);
                    }
                    foreach (($notificationUxon->getProperty('recipient_users')) ?? [] as $recipient) {
                        $stateDetails .= "<br>- " . str_replace(';', '#59;', $recipient);
                    }
                    foreach (($notificationUxon->getProperty('recipients')) ?? [] as $recipient) {
                        $stateDetails .= "<br>- " . str_replace(';', '#59;', $recipient);
                    }
                }
            }
            $stateDetails = $stateDetails !== '' ? '<br>' . $stateDetails : '';
            $stateDetails = str_replace(':', '#58;', $stateDetails);
            
            $mm .= <<<MERMAID

    {$state->getStateId()} : <span title="$stateProps">{$state->getName()} <small>{$stateFlags}</small><small>{$stateDetails}</small></span>
MERMAID;
            if ($state->isStartState()) {
                $mm .= <<<MERMAID
                
    [*] --> {$state->getStateId()}
MERMAID;
            }
            if ($state->isEndState()) {
                $mm .= <<<MERMAID
                
    {$state->getStateId()} --> [*]
MERMAID;
            }
            foreach ($state->getTransitions(false) as $targetStateId => $actionAlias) {
                if ($targetStateId === $state->getStateId() && ! $actionAlias) {
                    continue;
                }
                $actionAlias = '<span title="' . $actionAlias . '">' . StringDataType::substringAfter($actionAlias, '.', '', false, true) . '</span>';
                $mm .= <<<MERMAID
               
    {$state->getStateId()} --> {$targetStateId} : {$actionAlias}
MERMAID;
            }
        }
        $mm = trim($mm);
        
        return <<<MD
## State diagram
```mermaid
stateDiagram-v2
    $mm
```

MD;
    }
}