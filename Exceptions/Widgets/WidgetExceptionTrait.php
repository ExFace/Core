<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Exceptions\WidgetExceptionInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;

/**
 * This trait enables an exception to output widget specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait WidgetExceptionTrait {
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $widget = null;

    public function __construct(WidgetInterface $widget, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setWidget($widget);
    }

    /**
     * Returns the widget, that produced the error.
     *
     * @return \exface\Core\Interfaces\WidgetInterface
     */
    public function getWidget()
    {
        return $this->widget;
    }

    /**
     * Sets the widget, that produced the error.
     *
     * @param WidgetInterface $widget            
     * @return \exface\Core\Exceptions\Widgets\WidgetExceptionTrait
     */
    protected function setWidget(WidgetInterface $widget) : WidgetExceptionInterface
    {
        $this->widget = $widget;
        return $this;
    }

    public function getLinks() : array
    {
        $links = parent::getLinks();
        $widget = $this->getWidget();
        $links['Widget type `' . $widget->getWidgetType() . '`'] = DocsFacade::buildUrlToDocsForUxonPrototype($widget);
        if ($widget->hasParent()) {
            $links['Widget type `' . $widget->getParent()->getWidgetType() . '`'] = DocsFacade::buildUrlToDocsForUxonPrototype($widget->getParent());
        }
        return $links;
    }
    
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        return $this->getWidget()->createDebugWidget($debug_widget);
    }
}