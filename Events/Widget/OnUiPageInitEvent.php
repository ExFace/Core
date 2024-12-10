<?php
namespace exface\Core\Events\Widget;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Event fired after all widget of a UI page were initialized.
 *
 * @event exface.Core.Widget.OnUiPageInit
 *
 * @author Andrej Kabachnik
 *        
 */
class OnUiPageInitEvent extends AbstractEvent
{
    private $page = null;
    
    /**
     * 
     * @param WidgetInterface $dataSheet
     */
    public function __construct(UiPageInterface $page)
    {
        $this->page = $page;
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
    
    /**
     * 
     * @return UiPageInterface
     */
    public function getPage() : UiPageInterface
    {
        return $this->page;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnUiPageInit';
    }
}