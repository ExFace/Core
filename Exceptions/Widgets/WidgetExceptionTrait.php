<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\CommonLogic\UxonObject;

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
    public function setWidget(WidgetInterface $widget)
    {
        $this->widget = $widget;
        return $this;
    }

    
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        return $this->getWidget()->createDebugWidget($debug_widget);
    }
}