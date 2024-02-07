<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iCanEditData;
use exface\Core\CommonLogic\UxonObject;

/**
 * This trait contains common methods to implement the iCanEditData interface.
 * 
 * @author Andrej Kabachnik
 */
trait iCanEditDataTrait {
    
    use iCanBeEditableTrait;
    
    private $editable_changes_reset_on_refresh = true;
    
    private $editable_if_access_to_action_alias = null;
    
    /**
     *
     * @see \exface\Core\Interfaces\Widgets\iCanBeEditable::getEditableChangesResetOnRefresh()
     */
    public function getEditableChangesResetOnRefresh() : bool
    {
        return $this->editable_changes_reset_on_refresh;
    }
    
    /**
     * Set to FALSE to make changes in editable columns survive refreshes.
     *
     * By default, any changes, that were not saved explicitly, will be lost
     * as soon as the widget is refreshed - that is if a search is performed
     * or the data is sorted, etc. If this `editable_changes_reset_on_refresh`
     * is set to `false`, changes made in editable columns will "survive"
     * refreshes. On the other hand, there will be no possibility to revert
     * them, unless there is a dedicated reset-button (e.g. one with action
     * `exface.Core.ResetWidget`).
     *
     * @uxon-property editable_changes_reset_on_refresh
     * @uxon-type boolean
     * @uxon-default true
     *
     * @see \exface\Core\Interfaces\Widgets\iCanEditData::setEditableChangesResetOnRefresh()
     */
    public function setEditableChangesResetOnRefresh(bool $value) : iCanEditData
    {
        $this->editable_changes_reset_on_refresh = $value;
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function getEditableIfAccessToAction() : string
    {
        return $this->editable_if_access_to_action_alias;
    }
    
    /**
     * If a user has access to this action (alias), the widget will be editable.
     * 
     * This is typically the action, that is going to be used to save the edited data - e.g. `exface.Core.UpdateData`.
     * 
     * @uxon-property editable_if_access_to_action
     * @uxon-type metamodel:action 
     * 
     * @param string $value
     * @return iCanEditData
     */
    public function setEditableIfAccessToAction(string $value) : iCanEditData
    {
        $this->editable_if_access_to_action_alias = $value;
        return $this;
    }
    
    protected function exportUxonForEditableProperties() : UxonObject
    {
        $uxon = new UxonObject();
        if ($this->is_editable !== null) {
            $uxon->setProperty('editable', $this->is_editable);
        }
        if ($this->editable_if_access_to_action_alias !== null) {
            $uxon->setProperty('editable_if_access_to_action', $this->editable_if_access_to_action_alias);
        }
        if ($this->editable_changes_reset_on_refresh !== null) {
            $uxon->setProperty('editable_changes_reset_on_refresh', $this->editable_changes_reset_on_refresh);
        }
        return $uxon;
    }
}