<?php
namespace exface\Core\Events\Facades;

use exface\Core\Events\AbstractEvent;
use exface\Core\Interfaces\Events\FacadeEventInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Events\WidgetEventInterface;

/**
 * Event fired when a facade initializes rendering extensions for a widget and/or its part.
 * 
 * Some complex widgets (like `Map`s or `Chart`s) have so many options, plugins, etc., that 
 * it is impossible to implement them all in the widget model out-of-the-box. Instead
 * these widgets include widget parts, that can easily be extended by adding more PHP
 * classes and referencing them in the configuration of the respecitve widget part.
 * 
 * This means, when a facade is rendering the widget, it may not know all the different
 * widget parts used. By firing this event it tells the widget parts to try and register
 * renderers for them (if they have a renderer compatible with this facade). 
 * 
 * See `base_map` parts of the `Map` widget for examples how this can work. The exact definition
 * of what a renderer can da or how it is to be registered is up to the facade implementation.
 * 
 * @event exface.Core.Facades.OnFacadeWidgetRendererExtended
 * 
 * @author Andrej Kabachnik
 *
 */
class OnFacadeWidgetRendererExtendedEvent extends AbstractEvent implements FacadeEventInterface, WidgetEventInterface
{
    private $facade = null;
    
    private $widget = null;
    
    private $facadeElement = null;
    
    public function __construct(FacadeInterface $facade, WidgetInterface $widget, $facadeElement = null)
    {
        $this->facade = $facade;
        $this->widget = $widget;
        $this->facadeElement = $facadeElement;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->facade->getWorkbench();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\FacadeEventInterface::getFacade()
     */
    public function getFacade(): FacadeInterface
    {
        return $this->facade;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Events\WidgetEventInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * @return mixed
     */
    public function getFacadeElement()
    {
        return $this->facadeElement;
    }
}