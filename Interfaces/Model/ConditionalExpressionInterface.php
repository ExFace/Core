<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * A conditional expression is either a condition or a condition group.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ConditionalExpressionInterface extends WorkbenchDependantInterface, iCanBeCopied, iCanBeConvertedToUxon
{
    /**
     * 
     * @return ConditionGroupInterface
     */
    public function toConditionGroup() : ConditionGroupInterface;
    
    /**
     *
     * @return string
     */
    public function toString() : string;
    
    /**
     *
     * @return bool
     */
    public function isEmpty() : bool;
}

