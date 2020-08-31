<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\UiMenuItemEventInterface;
use exface\Core\Interfaces\Model\UiMenuItemInterface;

/**
 * Event fired after a UI menu item had been instantiated and it's model was loaded.
 * 
 * Listeners to this even can perform can modify properties of the page or add
 * page groups, etc.
 * 
 * @event exface.Core.Model.OnUiMenuItemLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnUiMenuItemLoadedEvent extends AbstractEvent implements UiMenuItemEventInterface
{
    
    private $menuItem = null;
    
    /**
     * 
     * @param UiMenuItemInterface $menuItem
     */
    public function __construct(UiMenuItemInterface $menuItem)
    {
        $this->menuItem = $menuItem;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\UiMenuItemEventInterface::getMenuItem()
     */
    public function getMenuItem() : UiMenuItemInterface
    {
        return $this->menuItem;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->menuItem->getWorkbench();
    }
}