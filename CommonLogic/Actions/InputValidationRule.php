<?php
namespace exface\Core\CommonLogic\Actions;

use exface\Core\Interfaces\Actions\InputValidationRuleInterface;
use exface\Core\CommonLogic\Model\ConditionGroup;

class InputValidationRule extends ConditionGroup implements InputValidationRuleInterface
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\InputValidationRuleInterface::setMessage()
     */
    public function setMessage(string $text): InputValidationRuleInterface
    {}

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\InputValidationRuleInterface::setMessageType()
     */
    public function setMessageType(string $type): InputValidationRuleInterface
    {}
}