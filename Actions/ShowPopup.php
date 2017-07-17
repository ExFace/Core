<?php
namespace exface\Core\Actions;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Widgets\Dialog;
use exface\Core\Interfaces\Actions\iShowPopup;
use exface\Core\Widgets\Container;

class ShowPopup extends ShowWidget implements iShowPopup
{

    /**
     * Creates a container widget for the popup.
     * If not contents is passed, an empty dialog widget will be returned.
     *
     * This method is called if there is no widget passed to the action or the 
     * passed widget is not a container. It creates a basic container and 
     * optionally fills it with the given content. By overriding this method,
     * you can change the way non-container widgets are handled.
     *
     * @return Container
     */
    protected function createPopupContainer(AbstractWidget $contained_widget = NULL)
    {
        $popup = $this->getCalledOnUiPage()->createWidget('Container', $this->getCalledByWidget());
        $popup->setMetaObject($this->getMetaObject());
        
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
        if (! ($widget instanceof Container)) {
            $widget = $this->createPopupContainer($widget);
            $this->setWidget($widget);
        }
        
        return $widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\iShowPopup::getPopupContainer()
     */
    public function getPopupContainer()
    {
        return $this->getWidget();
    }

}
?>