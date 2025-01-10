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
    
    private $collapsible = null;
    
    private $collapsed = false;
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::isCollapsible()
     */
    public function isCollapsible(bool $default = false) : bool
    {
        return $this->collapsible ?? $default;
    }
    
    /**
     * Set to TRUE to allow the user to collapse (minimize) the widget and to FALSE to prohibit it.
     * 
     * @uxon-property collapsible
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsible()
     */
    public function setCollapsible(bool $value) : iAmCollapsible
    {
        $this->collapsible = $value;
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
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsed()
     */
    public function setCollapsed(bool $trueOrFalse) : iAmCollapsible
    {
        $this->collapsed = $trueOrFalse;
        // Make sure the widget is automatically collapsible if it is set to be collapsed!
        if ($trueOrFalse === true) {
            $this->setCollapsible(true);
        }
        return $this;
    }
}