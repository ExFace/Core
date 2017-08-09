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
        if ($this->mustDestroyWidget()){
            $this->cleanupPageCache(); 
        }
    }
    
    /**
     * Returns TRUE if this is a critical exception and the widget must be destroyed 
     * and caches cleaned up.
     * 
     * @return boolean
     */
    protected function mustDestroyWidget()
    {
        return false;
    }
    
    /**
     * Ist die Widget-Konfiguration fehlerhaft wird das entsprechende Widget entfernt.
     * Ueber ein Event (Widget.Remove.After) wird das Element auch aus dem Element-
     * Cache des Templates entfernt (siehe AbstractAjaxTemplate->init()).
     * 
     * @return \exface\Core\Exceptions\Widgets\WidgetExceptionTrait
     */
    protected function cleanupPageCache()
    {
        $this->getWidget()->getPage()->removeWidget($this->getWidget());
        return $this;
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
            $request_widget->setValue('<pre>' . (! $this->getWidget()->exportUxonObjectOriginal()->isEmpty() ? $this->getWidget()->exportUxonObjectOriginal()->toJson(true) : $this->getWidget()->exportUxonObject()->toJson(true)) . '</pre>');
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }
}
?>