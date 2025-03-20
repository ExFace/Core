<?php
namespace exface\Core\Actions;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Actions\iRefreshInputWidget;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action refreshes the data in it's input widget - e.g. a DataTable, a Form, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class RefreshWidget extends ReadData implements iRefreshInputWidget
{
    private $reset_pagination = false;
    
    public function init()
    {
        parent::init();
        $this->setIcon(Icons::REFRESH);
        $this->setConfirmationForUnsavedChanges(true);
    }

    /**
     * 
     * @return boolean
     */
    public function getResetPagination()
    {
        return $this->reset_pagination;
    }
    
    /**
     * Set to TRUE to move back to the first page (if pagination is used).
     * 
     * @uxon-property reset_pagination
     * @uxon-type boolean
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Actions\RefreshWidget
     */
    public function setResetPagination($true_or_false)
    {
        $this->reset_pagination = BooleanDataType::cast($true_or_false);
        return $this;
    }
}
?>