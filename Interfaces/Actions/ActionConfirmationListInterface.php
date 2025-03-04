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
    
    public function setDisabled(bool $trueOrFalse): ActionConfirmationListInterface;
    
    public function isDisabled() : bool;

    /**
     * Make the action ask for confirmation when its button is pressed
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return ActionConfirmationListInterface
     */
    public function addFromUxon(UxonObject $uxon) : ActionConfirmationListInterface;

    /**
     * 
     * @return ConfirmationWidgetInterface|null
     */
    public function getConfirmationForAction() : ?ConfirmationWidgetInterface;

    public function hasConfirmationForAction() : bool;

    /**
     * 
     * @return ConfirmationWidgetInterface|null
     */
    public function getConfirmationForUnsavedChanges() : ?ConfirmationWidgetInterface;

    /**
     * 
     * @param bool|null $default
     * @return bool|null
     */
    public function hasConfirmationForUnsavedChanges(?bool $default = false) : ?bool;
}