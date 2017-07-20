<?php
namespace exface\Core\Interfaces\Actions;

/**
 * Actions, that refresh their input widget should implement this interface.
 *
 * @author Andrej Kabachnik
 *        
 */
interface iRefreshInputWidget extends iReadData
{
    /**
     * 
     * @return boolean
     */
    public function getResetPagination();
    
    /**
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Actions\RefreshWidget
     */
    public function setResetPagination($true_or_false);
}