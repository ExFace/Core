<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\BooleanDataType;

/**
 * A special menu to be used in data widgets to create context-menus.
 * 
 * In addition to a regular menu, it contains options to automatically import
 * buttons from the data widget and global actions for the underlying meta
 * object.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataRowMenu extends Menu
{

    private $include_data_buttons = true;

    private $include_global_actions = true;

    private $include_object_basket_actions = false;

    /**
     * 
     * @return Data
     */
    public function getDataWidget()
    {
        return $this->getInputWidget();
    }
    
    /**
     * 
     * @return boolean
     */
    public function getIncludeDataButtons()
    {
        return $this->include_data_buttons;
    }
    
    /**
     * 
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataRowMenu
     */
    public function setIncludeDataButtons($true_or_false)
    {
        $this->include_data_buttons = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
     *
     * @return boolean
     */
    public function getIncludeGlobalActions()
    {
        return $this->include_global_actions;
    }
    
    /**
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataRowMenu
     */
    public function setIncludeGlobalActions($true_or_false)
    {
        $this->include_global_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
    
    /**
     *
     * @return boolean
     */
    public function getIncludeObjectBasketActions()
    {
        return $this->include_object_basket_actions;
    }
    
    /**
     *
     * @param boolean $true_or_false
     * @return \exface\Core\Widgets\DataRowMenu
     */
    public function setIncludeObjectBasketActions($true_or_false)
    {
        $this->include_object_basket_actions = BooleanDataType::parse($true_or_false);
        return $this;
    }
 
}
?>