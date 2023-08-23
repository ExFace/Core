<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Widgets\iCanEditData;

/**
 * This trait contains common methods to implement the iCanEditData interface.
 * 
 * @author Andrej Kabachnik
 */
trait iCanEditDataTrait {
    
    /** @var boolean */
    private $is_editable = false;
    
    private $editable_changes_reset_on_refresh = true;
    
    
    
    /**
     * Returns TRUE, if the data widget contains at least one editable column or column group.
     *
     * @see \exface\Core\Interfaces\Widgets\iCanEditData::isEditable()
     */
    public function isEditable() : bool
    {
        return $this->is_editable;
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
     * @see \exface\Core\Interfaces\Widgets\iCanEditData::setEditable()
     */
    public function setEditable(bool $value = true) : iCanEditData
    {
        $this->is_editable = $value;
        return $this;
    }
    
    /**
     *
     * @see \exface\Core\Interfaces\Widgets\iCanEditData::getEditableChangesResetOnRefresh()
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
}