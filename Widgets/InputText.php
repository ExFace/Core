<?php
namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * Multi-line text input (similar to HTML <textarea>).
 * 
 * @author Andrej Kabachnik
 *
 */
class InputText extends Input
{
    use SingleValueInputTrait;
    
    private $showCharacterLimit = true;
    
    /**
     * Returns TRUE if the remaining character counter should be shown when the value
     * data type has a maximum length, FALSE otherwise.
     * 
     * @return bool
     */
    public function getShowCharacterLimit() : bool
    {
        return $this->showCharacterLimit;
    }
    
    /**
     * Set to FALSE to hide the remaining character counter
     * 
     * @uxon-property show_character_limit
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return InputText
     */
    public function setShowCharacterLimit(bool $value) : InputText
    {
        $this->showCharacterLimit = $value;
        return $this;
    }
}