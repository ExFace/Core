<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Actions\iShowPopup;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Shows a popup with any contents specified in the widget-property
 * 
 * @author Andrej Kabachnik
 *
 */
class ShowPopup extends ShowWidget implements iShowPopup
{

    /**
     * Creates a container widget for the popup.
     * If not contents is passed, an empty container widget will be returned.
     *
     * This method is called if there is no widget passed to the action or the 
     * passed widget is not a container. It creates a basic container and 
     * optionally fills it with the given content. By overriding this method,
     * you can change the way non-container widgets are handled.
     * 
     * @param UiPageInterface $page
     * @param WidgetInterface $contained_widget
     *
     * @return Container
     */
    protected function createPopupContainer(UiPageInterface $page, WidgetInterface $contained_widget = NULL) : iContainOtherWidgets
    {
        if ($this->isDefinedInWidget()) {
            $popup = WidgetFactory::create($page, 'Container', $this->getWidgetDefinedIn());
            $popup->setMetaObject($this->getMetaObject());
        } else {
            $popup = WidgetFactory::create($page, 'Container');
        }
        
        if ($contained_widget) {
            $popup->addWidget($contained_widget);
        }
        
        return $popup;
    }

    /**
     * Returns the container widget to be show in the popup.
     * 
     * If a non-container is passed to the action, it will be automatically
     * wrapped by a basic container widget.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Actions\ShowWidget::getWidget()
     */
    public function getWidget()
    {
        $widget = parent::getWidget();
        if (is_null($widget)) {
            try {
                $page = $this->getWidgetDefinedIn()->getPage();
            } catch (\Throwable $e) {
                $page = UiPageFactory::createEmpty($this->getWorkbench());
            }
            $widget = $this->createPopupContainer($page);
            $this->setWidget($widget);
        } elseif (! ($widget instanceof Container)) {
            $widget = $this->createPopupContainer($page, $widget);
            $this->setWidget($widget);
        }
        
        return $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowPopup::getPopupContainer()
     */
    public function getPopupContainer() : iContainOtherWidgets
    {
        return $this->getWidget();
    }
}