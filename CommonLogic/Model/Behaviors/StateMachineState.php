<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iModifyData;
use exface\Core\Interfaces\Actions\iCreateData;
use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Behaviors\StateMachineBehavior;
use exface\Core\Exceptions\Behaviors\BehaviorConfigurationError;
use exface\Core\CommonLogic\Traits\TranslatablePropertyTrait;
use exface\Core\Exceptions\RuntimeException;

/**
 * Defines a state for the StateMachineBehavior.
 *
 * @author SFL
 */
class StateMachineState
{
    use TranslatablePropertyTrait;

    private $state_id = null;

    private $buttons = [];

    private $disabled_attributes_aliases = [];
    
    private $disable_editing = false;
    
    private $disable_delete = false;

    private $transitions = null;

    private $name = null;

    private $name_translation_key = null;
    
    private $color = null;
    
    private $stateMachine = null;
    
    public function __construct(StateMachineBehavior $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    /**
     * Returns the state id.
     *
     * @return integer|string
     */
    public function getStateId()
    {
        return $this->state_id;
    }

    /**
     * Defines the state id.
     *
     * @param integer|string $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    public function setStateId($value)
    {
        $this->state_id = $value;
        return $this;
    }

    /**
     * Returns the buttons for the state.
     *
     * @return UxonObject
     */
    public function getButtons()
    {
        return $this->buttons;
    }

    /**
     * Defines the buttons for the state.
     *
     * Example:
     * 
     * ```
     * {
     *  "states": [
     *      "10": { 
     *          "name": "Created",
     *          "buttons": [
     *              {
     *                  "caption": "20 Confirm",
     *                  "action": {
     *                      "alias": "exface.Core.UpdateData",
     *                      "input_data_sheet": {
     *                          "object_alias": "alexa.RMS.CUSTOMER_COMPLAINT",
     *                          "columns": [
     *                              {
     *                                  "attribute_alias": "STATE_ID",
     *                                  "formula": "=NumberValue('20')"
     *                              },
     *                              {
     *                                  "attribute_alias": "TS_UPDATE"
     *                              }
     *                          ]
     *                      }
     *                  }
     *              }
     *          ]
     *      }
     *  }
     *  
     * ```
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\Button[]
     * @uxon-template {"10": {"caption": "", "action": {"alias": ""}}, "20": {"caption": "", "action": {"alias": ""}}}
     *
     * @param UxonObject $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    public function setButtons($value)
    {
        $this->buttons = $value;
        return $this;
    }

    /**
     * Defines the disabled attributes aliases for the state.
     *
     * Example:
     * 
     * ```
     * {
     *  "states": [
     *      "20": {
     *          "name": "Confirmed",
     *          "disabled_attribute_aliases": [
     *              "DOCUMENT_NO",
     *              "DOCUMENT_DATE"
     *          ]
     *      }
     *  ]
     * }
     *  
     * ```
     * 
     * @uxon-property disabled_attributes_aliases
     * @uxon-type metamodel:attribute[]
     * @uxon-template [""]
     * 
     * @param UxonObject|string[] $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    public function setDisabledAttributesAliases($value)
    {
        if ($value instanceof UxonObject){
            $array = $value->toArray();
        } elseif (is_array($value)){
            $array = $value;
        } else {
            throw new UnexpectedValueException('Invalid format for disabled attribute aliases for StateMachineBehavior! Array expected!');
        }
        $this->disabled_attributes_aliases = $array;
        return $this;
    }

    /**
     * Returns the disabled attributes aliases for the state.
     *
     * @return string[]
     */
    public function getDisabledAttributesAliases()
    {
        return $this->disabled_attributes_aliases;
    }
    
    /**
     * Returns TRUE if transitions were defined for this state and FALSE otherwise.
     * 
     * @return bool
     */
    protected function hasTransitionRestrictions() : bool
    {
        return $this->transitions !== null;
    }

    /**
     * Returns the allowed transitions for the state.
     *
     * @return integer[]
     */
    public function getTransitions()
    {
        return $this->transitions ?? [];
    }

    /**
     * Defines the allowed transitions for the state.
     *
     * The below example illustrates a state machine with the following rules. 
     * From state 10 any state can be reached except 80. In state 20 too, but
     * there is no going back to 10. From only transitions to 80 or 99 are
     * allowed. In 80 an object can be saved, but the state cannot change,
     * while in state 99 no writing to the instance is possible (even without
     * changing the state!).
     *  {
     *      10: {
     *          transitions: [ 10, 20, 50, 99 ]
     *      },
     *      20: {
     *          transitions: [ 20, 50, 99 ]
     *      },
     *      50: {
     *          transitions: [ 80, 99 ]
     *      },
     *      80: {
     *          transitions: [ 80 ]
     *      },
     *      99: {
     *          transitions: []
     *      }
     *  } 
     *  
     * @uxon-property transitions
     * @uxon-type array
     * @uxon-template ["10", "20", "99"]
     *
     * @param UxonObject|integer[] $value            
     * @return \exface\Core\CommonLogic\Model\Behaviors\StateMachineState
     */
    public function setTransitions($value)
    {
        if ($value instanceof UxonObject){
            $array = $value->toArray();
        } elseif (is_array($value)){
            $array = $value;
        } else {
            throw new UnexpectedValueException('Invalid format for transitions definition ins StateMachineBehavior! Array expected!');
        }
        $this->transitions = $array;
        return $this;
    }

    /**
     * Defines the name for the state (use =TRANSLATE() for translatable names).
     * 
     * @uxon-property name
     * @uxon-type string|metamodel:formula
     *
     * @param string $name            
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the name of the state.
     * 
     * If the name was specified as a formula (e.g. =TRANSLATE(...)), the formula will be evaluated.
     *
     * @return string
     */
    public function getName($prependId = false)
    {
        if ($this->name !== null && $this->isValueFormula($this->name) === true) {
            try {
                $this->name = $this->evaluatePropertyExpression($this->name);
            } catch (RuntimeException $e) {
                throw new BehaviorConfigurationError($this->getStateMachine()->getObject(), 'Invalid value for state name "' . $this->name . '": only strings and static formulas like =TRANSLATE() are allowed!', $e->getAlias(), $e);
            }
        }
        return ($prependId === true ? $this->getStateId() . ' ' : '') . $this->name;
    }
    
    /**
     * Sets a custom color for this state (used in all sorts of indicators like progress bars).
     * 
     * You can use hexadecimal color codes or HTML color names.
     * 
     * @uxon-property color
     * @uxon-type color
     * 
     * @param string $color_name_or_code
     * @return StateMachineState
     */
    public function setColor($color_name_or_code)
    {
        $this->color = $color_name_or_code;
        return $this;
    }
    
    /**
     * 
     * @return string|null
     */
    public function getColor() : ?string
    {
        return $this->color;
    }

    /**
     * 
     * @return bool
     */
    public function getDisableEditing() : bool
    {
        return $this->disable_editing;
    }

    /**
     * Prevents instances of the object from being edited/changed in this state if set to TRUE.
     * 
     * This is a shortcut to putting all editable attributes into disabled_attribute_aliases.
     * 
     * @uxon-property disable_editing
     * @uxon-type bool
     * @uxon-default false
     * 
     * @param int|string|bool $trueOrFalse
     * @return StateMachineState
     */
    public function setDisableEditing($trueOrFalse) : StateMachineState
    {
        $this->disable_editing = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getDisableDelete() : bool
    {
        return $this->disable_delete;
    }

    /**
     * Prevents instances of the object in the current state from being deleted if set to TRUE.
     * 
     * @uxon-property disable_delete
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param int|string|bool $trueOrFalse
     * @return StateMachineState
     */
    public function setDisableDelete($trueOrFalse) : StateMachineState
    {
        $this->disable_delete = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

    /**
     * Returns TRUE if a transition from this state to the given one is possible.
     * 
     * @param StateMachineState $toState
     * @return bool
     */
    public function isTransitionAllowed(StateMachineState $toState) : bool
    {
        return $this->hasTransitionRestrictions() === false || in_array($toState->getStateId(), $this->getTransitions()) === true;
    }
    
    /**
     * Returns TRUE if the editing of the given attribute is not blocked by this state and FALSE otherwise.
     * 
     * @param MetaAttributeInterface $attribute
     * @return bool
     */
    public function isAttributeDisabled(MetaAttributeInterface $attribute) : bool
    {
        return $this->getDisableEditing() === true || in_array($attribute->getAliasWithRelationPath(), $this->getDisabledAttributesAliases()) === true;
    }

    /**
     * Returns TRUE if the given action is forbidden in this state.
     * 
     * This is the case if the action modifies or deletes data and the state has `disable_editing` = true.
     * 
     * @param ActionInterface $action
     * @return bool
     */
    public function isActionDisabled(ActionInterface $action) : bool
    {
        return $this->getDisableEditing() === true && ($action instanceof iModifyData) && ! ($action instanceof iCreateData);
    }
    
    /**
     * 
     * @return StateMachineBehavior
     */
    public function getStateMachine() : StateMachineBehavior
    {
        return $this->stateMachine;
    }
}
