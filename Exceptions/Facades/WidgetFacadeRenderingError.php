<?php
namespace exface\Core\Exceptions\Facades;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Exceptions\Widgets\WidgetExceptionTrait;
use exface\Core\Interfaces\Exceptions\FacadeExceptionInterface;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Exception thrown if a facade fails to render a widget, initialize the facade element, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetFacadeRenderingError extends RuntimeException implements WidgetExceptionInterface, FacadeExceptionInterface
{
    use WidgetExceptionTrait;
    
    private ?FacadeInterface $facade = null;
    
    public function __construct(WidgetInterface $widget, FacadeInterface $facade, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setWidget($widget);
        $this->facade = $facade;
    }

    /**
     * {@inheritDoc}
     * @see FacadeExceptionInterface::getFacade()
     */
    public function getFacade() : FacadeInterface
    {
        return $this->facade;
    }
}