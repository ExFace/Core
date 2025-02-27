<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * This trait helps getting the input widget for action triggers.
 * 
 * @author Andrej Kabachnik
 * 
 * @method UiPageInterface getPage()
 *
 */
trait iUseInputWidgetTrait {
    
    private $input_widget_id = null;
    
    private $input_widget = null;

    /**
     * Returns the input widget of the button.
     *
     * If no input widget was set for this button explicitly (via UXON or
     * programmatically using setInputWidget()), the input widget will be
     * determined automatically:
     * - If the parent of the button is a button or a button group, the input
     * widget will be inherited
     * - If the parent of the widget has buttons (e.g. a Data widget), it will
     * be used as input widget
     * - Otherwise the search for those criteria will continue up the hierarchy
     * untill the root widget is reached. If no match is found, the root widget
     * itself will be returned.
     *
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseInputWidget::getInputWidget()
     */
    public function getInputWidget() : WidgetInterface
    {
        if ($this->input_widget === null) {
            if ($this->input_widget_id) {
                $page = $this->getPage();
                if (mb_strpos($this->input_widget_id, $page->getWidgetIdSpaceSeparator()) === false && $idSpace = $this->getIdSpace()) {
                    $widgetId = $idSpace . $page->getWidgetIdSpaceSeparator() . $this->input_widget_id;
                } else {
                    $widgetId = $this->input_widget_id;
                }
                $this->input_widget = $this->getPage()->getWidget($widgetId);
            } elseif ($this->hasParent()) {
                $parent = $this->getParent();
                while (!(($parent instanceof iHaveButtons) || ($parent instanceof iUseInputWidget)) && ! is_null($parent->getParent())) {
                    $parent = $parent->getParent();
                }
                if ($parent instanceof iUseInputWidget){
                    $this->input_widget = $parent->getInputWidget();
                } else {
                    $this->input_widget = $parent;
                }
            }
        }
        return $this->input_widget ?? $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iUseInputWidget::setInputWidget()
     */
    public function setInputWidget(WidgetInterface $widget) : iUseInputWidget
    {
        $this->input_widget = $widget;
        $this->setInputWidgetId($widget->getId());
        return $this;
    }
    
    /**
     * Returns the id of the widget, which the action is supposed to be performed upon.
     * I.e. if it is an Action doing something with a table row, the input widget will be
     * the table. If the action ist to be performed upon an Input field - that Input is the input widget.
     *
     * By default the input widget is the actions parent
     */
    public function getInputWidgetId()
    {
        if (! $this->input_widget_id) {
            if ($this->input_widget) {
                $this->setInputWidgetId($this->getInputWidget()->getId());
            } else {
                $this->setInputWidgetId($this->getParent()->getId());
            }
        }
        return $this->input_widget_id;
    }
    
    /**
     * Sets the id of the widget to be used to fetch input data for the action performed by this button.
     *
     * @uxon-property input_widget_id
     * @uxon-type uxon:$..id
     *
     * @param string $value
     */
    public function setInputWidgetId($value)
    {
        $this->input_widget_id = $value;
        return $this;
    }
}