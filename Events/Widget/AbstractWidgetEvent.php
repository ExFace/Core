<?php
namespace exface\Core\Events\Widget;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\UiPageEventInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Events\WidgetEventInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
abstract class AbstractWidgetEvent extends AbstractEvent implements WidgetEventInterface, UiPageEventInterface
{
    private $widget = null;
    
    /**
     * 
     * @param WidgetInterface $dataSheet
     */
    public function __construct(WidgetInterface $widget)
    {
        $this->widget = $widget;
    }

    /**
     * 
     * @return WidgetInterface
     */
    public function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\UiPageEventInterface::getPage()
     */
    public function getPage() : UiPageInterface
    {
        return $this->widget->getPage();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->widget->getWorkbench();
    }
}