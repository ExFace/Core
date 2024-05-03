<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\Interfaces\DataSheets\DataCheckListInterface;

/**
 *
 * @author Andrej Kabachnik *        
 */
interface ActionDataCheckListInterface extends DataCheckListInterface
{
    public function getAction() : ActionInterface;
    
    public function setDisabled(bool $trueOrFalse): ActionDataCheckListInterface;
    
    public function isDisabled() : bool;
}