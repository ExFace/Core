<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\CommonLogic\Constants\Colors;

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
     * @return \exface\Core\Behaviors\StateMachineState
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
     * @return \exface\Core\Behaviors\StateMachineState
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
     * @return \exface\Core\Behaviors\StateMachineState
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
     * @return \exface\Core\Behaviors\StateMachineState
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
    public function getName()
    {
        return $this->name;
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
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }
}
?>
