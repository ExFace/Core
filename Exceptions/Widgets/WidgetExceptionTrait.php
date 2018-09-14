<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\ExceptionTrait;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

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
        if ($debug_widget->findChildById('widget_uxon_tab') === false) {
            $page = $debug_widget->getPage();
            $uxon_tab = $debug_widget->createTab();
            $uxon_tab->setId('widget_uxon_tab');
            $uxon_tab->setCaption('Widget UXON');
            $uxon_tab->setNumberOfColumns(1);
            $request_widget = WidgetFactory::create($page, 'Html');
            $uxon_tab->addWidget($request_widget);
            $uxon = $this->getWidget()->exportUxonObjectOriginal();
            if ($uxon->isEmpty()) {
                try {
                    $uxon = $this->getWidget()->exportUxonObject();
                } catch (\Throwable $e) {
                    // Do nothing - this will show the empty original UXON
                }
            }
            $request_widget->setHtml('<pre>' . $uxon->toJson(true) . '</pre>');
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }
}
?>