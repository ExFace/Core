<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Interfaces\Widgets\iAmCollapsible;

/**
 * This trait adds a default implementation of the iAmCollapsible interface.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iAmCollapsibleTrait {
    
    private $collapsible = false;
    
    private $collapsed = false;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::isCollapsible()
     */
    public function isCollapsible() : bool
    {
        return $this->collapsible;
    }
    
    /**
     * Set to TRUE to allow the user to collapse (minimize) the widget and to FALSE to prohibit it.
     * 
     * @uxon-property collapsible
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsible()
     */
    public function setCollapsible($value) : iAmCollapsible
    {
        $this->collapsible = BooleanDataType::cast($value);
        return $this;
    }
    
    public function isCollapsed() : bool
    {
        return $this->collapsed;
    }
    
    /**
     * Set to TRUE to render the widget collapsed (minimized) initially
     * 
     * @uxon-property collapsed
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsed()
     */
    public function setCollapsed($trueOrFalse) : iAmCollapsible
    {
        $this->collapsed = BooleanDataType::cast($trueOrFalse);
        return $this;
    }
}