<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * A dashboard is a special type of panel, that contains independent Box-widgets.
 *
 * As the name suggests, it is usefull for creating dashboards as each box is totally independent
 * and may display absolutely different meta objects.
 *
 * @author Andrej Kabachnik
 *        
 */
class Dashboard extends Panel
{
    
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = NULL)
    {
        if (! ($widget instanceof Box)) {
            $box = WidgetFactory::create($this->getPage(), 'Box', $this);
            $box->addWidget($widget);
            if ($widget->getHeight()->isUndefined() === false && $widget->getHeight()->isMax() === false) {
                $box->setHeight($widget->getHeight()->getValue());
            }
            if ($widget->getWidth()->isUndefined() === false && $widget->getWidth()->isMax() === false) {
                $box->setWidth($widget->getWidth()->getValue());
            }
        } else {
            $box = $widget;
        }
        return parent::addWidget($box, $position);
    }
    
    /**
     * Widgets (cards) to show in the dashboard.
     * 
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\Box[]
     * @uxon-template [{"widgets":[{"":""},{"":""}]}] 
     * 
     * @see \exface\Core\Widgets\WidgetGrid::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $widgets = array();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof UxonObject) {
                // If we have a UXON or instantiated widget object, use the widget directly
                $widgets[] = WidgetFactory::createFromUxon($this->getPage(), $w, $this, 'Box');
            } else {
                // If it is something else, just add it to the result and let the parent object deal with it
                $widgets[] = $w;
            }
        }
        
        return parent::setWidgets($widgets);
    }
}
?>