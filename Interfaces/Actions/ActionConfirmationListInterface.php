<?php
namespace exface\Core\Interfaces\Actions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\ConfirmationWidgetInterface;

/**
 *
 * @author Andrej Kabachnik
 */
interface ActionConfirmationListInterface
{
    public function getAction() : ActionInterface;
    
    public function disableAll(bool $trueOrFalse) : ActionConfirmationListInterface;

    public function disableConfirmationsForUnsavedChanges(bool $trueOrFalse) : ActionConfirmationListInterface;

    public function disableConfirmationsForAction(bool $trueOrFalse) : ActionConfirmationListInterface;

    public function isPossible() : bool;

    /**
     * Make the action ask for confirmation when its button is pressed
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ActionConfirmationListInterface
     */
    public function addFromUxon(UxonObject $uxon) : ActionConfirmationListInterface;

    /**
     * 
     * @return ActionConfirmationListInterface
     */
    public function getConfirmationsForAction() : self;

    /**
     * 
     * @return ActionConfirmationListInterface
     */
    public function getConfirmationsForUnsavedChanges() : self;
}