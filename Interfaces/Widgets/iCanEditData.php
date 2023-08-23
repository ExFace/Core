<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * This is a common interface for editable data widgets like spreadsheets
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanEditData extends iShowData
{
    /**
     * Returns true, if the data table contains at least one editable column
     *
     * @return boolean
     */
    public function isEditable() : bool;
    
    /**
     * Set to TRUE to make the table editable or add a column with an editor.
     * FALSE by default.
     *
     * @param bool $value
     * @return iShowData
     */
    public function setEditable(bool $value = true) : iCanEditData;
    
    /**
     *
     * @return bool
     */
    public function getEditableChangesResetOnRefresh() : bool;
    
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
     * @param bool $value
     * @return iCanEditData
     */
    public function setEditableChangesResetOnRefresh(bool $value) : iCanEditData;
}