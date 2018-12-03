<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\DataTypes\BooleanDataType;

/**
 * Defines a state for the StateMachineBehavior.
 *
 * @author SFL
 */
class StateMachineState
{

    private $state_id = null;

    private $buttons = [];

    private $disabled_attributes_aliases = [];
    
    private $disable_editing = false;
    
    private $disable_delete = false;

    private $transitions = [];

    private $name = null;

    private $name_translation_key = null;
    
    private $color = null;

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
     *  {
     *      "20": {
     *          "caption": "20 Annahme bestätigen",
     *          "action": {
     *              "alias": "exface.Core.UpdateData",
     *              "input_data_sheet": {
     *                  "object_alias": "alexa.RMS.CUSTOMER_COMPLAINT",
     *                  "columns": [
     *                      {
     *                          "attribute_alias": "STATE_ID",
     *                          "formula": "=NumberValue('20')"
     *                      },
     *                      {
     *                          "attribute_alias": "TS_UPDATE"
     *                      }
     *                  ]
     *              }
     *          }
     *      }
     *  }
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
     *  [
     *      "COMPLAINT_NO"
     *  ]
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
     * Returns the allowed transitions for the state.
     *
     * @return integer[]
     */
    public function getTransitions()
    {
        return $this->transitions;
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
     * Defines the name for the state.
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
     * @return string
     */
    public function getName($prependId = false)
    {
        return ($prependId === true ? $this->getStateId() . ' ' : '') . $this->name;
    }

    /**
     * Defines the name_translation_key for the state.
     *
     * @param null $name_translation_key            
     */
    public function setNameTranslationKey($name_translation_key)
    {
        $this->name_translation_key = $name_translation_key;
    }

    /**
     * Returns the name_translation_key of the state.
     *
     * @return string
     */
    public function getNameTranslationKey()
    {
        return $this->name_translation_key;
    }

    /**
     *
     * @param TranslationInterface $translator            
     *
     * @return mixed
     */
    public function getStateName($translator)
    {
        $nameTranslationKey = $this->getNameTranslationKey();
        if ($nameTranslationKey) {
            $translation = $translator->translate($nameTranslationKey);
            if ($translation && $translation != $nameTranslationKey) {
                return $translation;
            }
        }
        
        $name = $this->getName();
        if ($name) {
            return $name;
        }
        
        return false;
    }
    
    /**
     * Sets a custom color for this state (used in all sorts of indicators like progress bars).
     * 
     * You can use hexadecimal color codes or HTML color names.
     * 
     * @uxon-property color
     * @uxon-type string
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
     * 
     * @param int|string|bool $trueOrFalse
     * @return StateMachineState
     */
    public function setDisableDelete($trueOrFalse) : StateMachineState
    {
        $this->disable_delete = BooleanDataType::cast($trueOrFalse);
        return $this;
    }

}
?>
