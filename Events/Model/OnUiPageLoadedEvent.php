<?php
namespace exface\Core\Events\Model;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\UiPageEventInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Event fired after a UI page had been instantiated and it's model was loaded.
 * 
 * Listeners to this even can perform can modify properties of the page or add
 * page groups, etc.
 * 
 * @event exface.Core.Model.OnUiPageLoaded
 *
 * @author Andrej Kabachnik
 *
 */
class OnUiPageLoadedEvent extends AbstractEvent implements UiPageEventInterface
{
    
    private $page = null;
    
    /**
     * 
     * @param UiPageInterface $page
     */
    public function __construct(UiPageInterface $page)
    {
        $this->page = $page;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\UiPageEventInterface::getPage()
     */
    public function getPage() : UiPageInterface
    {
        return $this->page;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->page->getWorkbench();
    }
}