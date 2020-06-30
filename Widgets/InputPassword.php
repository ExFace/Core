<?php
namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * A (masked) password input
 *
 * @author Andrej Kabachnik
 *
 */
class InputPassword extends Input
{
    use SingleValueInputTrait;
    
    private $inputForConformation = FALSE;
    
    /**
     * @uxon-property show_second_input_for_confirmation
     * @uxon-type string
     *
     * @param bool $trueOrFalse
     * @return InputPassword
     */
    public function setShowSecondInputForConfirmation(bool $trueOrFalse) : InputPassword
    {
        $this->inputForConformation = $trueOrFalse;
        return $this;
    }
    
    /**
     *
     * @return bool
     */
    public function getShowSecondInputForConfirmation() : bool
    {
        return $this->inputForConformation;
    }
}