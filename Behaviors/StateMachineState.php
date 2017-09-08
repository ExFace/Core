<?php
namespace exface\Core\Behaviors;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Exceptions\UnexpectedValueException;

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
     * {
     * "20": {
     * "caption": "20 Annahme best�tigen",
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
     * }
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
     * Example:
     * [
     * 10,
     * 20,
     * 30,
     * 50,
     * 60,
     * 70,
     * 90,
     * 99
     * ]
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
}
?>
