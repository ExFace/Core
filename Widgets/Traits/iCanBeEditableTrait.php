<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Factories\ActionFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iCanBeEditable;

/**
 * This trait contains common methods to implement the iCanBeEditable interface.
 * 
 * @author Andrej Kabachnik
 */
trait iCanBeEditableTrait {
    
    /** @var boolean */
    private $is_editable = false;
    
    /**
     * Returns TRUE, if the data widget contains at least one editable column or column group.
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeEditable::isEditable()
     */
    public function isEditable() : bool
    {
        $editableExplicitly = $this->is_editable;
        if ($editableExplicitly === true || $this->editable_if_access_to_action_alias === null) {
            return $editableExplicitly;
        }
        $action = ActionFactory::createFromString($this->getWorkbench(), $this->editable_if_access_to_action_alias, $this);
        return $action->isAuthorized() === true;
    }
    
    /**
     * Set to TRUE to make the column cells editable.
     *
     * This makes all columns editable, that are bound to an editable model
     * attribute or have no model binding at all. Editable column cells will
     * automatically use the default editor widget from the bound model attribute
     * as `cell_widget`.
     *
     * @uxon-property editable
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeEditable::setEditable()
     */
    public function setEditable(bool $value = true) : iCanBeEditable
    {
        $this->is_editable = $value;
        
        return $this;
    }
}