<?php
namespace exface\Core\Actions;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Actions\iRefreshInputWidget;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * This action refreshes the data in it's input widget - e.g. a DataTable.
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
        $this->setIconName(Icons::REFRESH);
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
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Actions\RefreshWidget
     */
    public function setResetPagination($true_or_false)
    {
        $this->reset_pagination = BooleanDataType::parse($true_or_false);
        return $this;
    }
}
?>