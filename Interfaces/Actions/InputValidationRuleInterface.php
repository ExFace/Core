<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\Model\ConditionGroupInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
interface InputValidationRuleInterface extends ConditionGroupInterface
{
    public function setMessage(string $text) : InputValidationRuleInterface;
    
    public function setMessageType(string $type) : InputValidationRuleInterface;
}