<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetLinkUnresolvableError extends RuntimeException implements WidgetExceptionInterface
{
    private WidgetLinkInterface $widgetLink;
    
    public function __construct(WidgetLinkInterface $widgetLink, string $message = '', ?string $alias = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->widgetLink = $widgetLink;
    }
    
    public function getWidgetLink(): WidgetLinkInterface
    {
        return $this->widgetLink;
    }

    /**
     * @inheritDoc
     */
    public function getWidget()
    {
        return $this->getWidgetLink()->getSourceWidget();
    }
}