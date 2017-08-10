<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\UnderflowException;

/**
 * The Container is a basic widget, that contains other widgets - typically simple ones like inputs.
 * The conainer itself is mostly invisible - it
 * is just a technical grouping element. Use it, if you just need to place multiple widgets somewhere, where only one widget is expected. The
 * Container is also a common base for many other wigdets: the Panel (a visible UI area, that contains other widgets), the Form, Tabs and Splits, etc.
 *
 * In HTML-templates the container will either be a simple (invisible) <div> or completely invisible - thus, just a list of it's contents without
 * any wrapper.
 *
 * @author Andrej Kabachnik
 *        
 */
class Container extends AbstractWidget implements iContainOtherWidgets
{

    private $widgets = array();

    protected function doPrefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet)
    {
        foreach ($this->getChildren() as $widget) {
            $widget->prefill($data_sheet);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        if ($this->getMetaObjectId() == $data_sheet->getMetaObject()->getId()) {
            foreach ($this->getChildren() as $widget) {
                $data_sheet = $widget->prepareDataSheetToRead($data_sheet);
            }
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        foreach ($this->getChildren() as $widget) {
            $data_sheet = $widget->prepareDataSheetToPrefill($data_sheet);
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = NULL)
    {
        if ($widget->getParent() !== $this){
            $widget->setParent($this);
        }
        
        if (is_null($position) || ! is_numeric($position)) {
            $this->widgets[] = $widget;
        } else {
            array_splice($this->widgets, $position, 0, array(
                $widget
            ));
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::addWidgets()
     */
    public function addWidgets(array $widgets)
    {
        foreach ($widgets as $widget) {
            $this->addWidget($widget);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren()
    {
        return $this->getWidgets();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::findChildById()
     */
    public function findChildById($widget_id)
    {
        foreach ($this->getChildren() as $child) {
            if (strcasecmp($child->getId(), $widget_id) === 0) {
                return $child;
            }
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getWidgets()
     */
    public function getWidgets(callable $filter = null)
    {
        if (!is_null($filter)){
            return array_filter($this->widgets, $filter);
        }
        return $this->widgets;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getWidget()
     */
    public function getWidget($index)
    {
        if (!is_int($index)){
            throw new \UnexpectedValueException('Invalid index "' . $index . '" used to search for a child widget!');
        }
        
        $widgets = $this->getWidgets();
        
        if (! array_key_exists($index, $widgets)){
            throw new WidgetChildNotFoundError($this, 'No child widget found with index "' . $index . '" in ' . $this->getWidgetType() . '!');
        }
        
        return $widgets[$index];
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getWidgetIndex()
     */
    public function getWidgetIndex(WidgetInterface $widget)
    {
        return array_search($widget, $this->getWidgets());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::hasWidgets()
     */
    public function hasWidgets()
    {
        return empty($this->widgets) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::isEmpty()
     */
    public function isEmpty()
    {
        return ! $this->hasWidgets();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::removeWidgets()
     */
    public function removeWidgets()
    {
        $this->widgets = array();
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::removeWidget()
     */
    public function removeWidget(WidgetInterface $widget)
    {
        $key = array_search($widget, $this->widgets);
        if ($key !== false){
            unset($this->widgets[$key]);
            // Reindex the array to avoid index gaps
            $this->widgets = array_values($this->widgets);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getWidgetFirst()
     */
    public function getWidgetFirst(callable $filter = null)
    {
        foreach ($this->getWidgets() as $widget){
            if (is_null($filter) || $filter($widget) === true){
                return $widget;
            }
        }
        throw new UnderflowException('Cannot get first widget from ' . $this->getWidgetType() . ': no widgets matching the filter were found!');
    }

    /**
     * Array of widgets in the container: each one is defined as a regular widget object.
     *
     * Widgets will be displayed in the order of definition. By default all widgets will inherit the container's meta object.
     *
     * @uxon-property widgets
     * @uxon-type \exface\Core\Widgets\AbstractWidget
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::setWidgets()
     */
    public function setWidgets(array $widget_or_uxon_array)
    {
        if (! is_array($widget_or_uxon_array))
            return false;
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof AbstractWidget) {
                $this->addWidget($w);
            } else {
                $page = $this->getPage();
                $widget = WidgetFactory::createFromUxon($page, UxonObject::fromAnything($w), $this);
                $this->addWidget($widget);
            }
        }
        return $this;
    }

    /**
     * If a container is disabled, all children widgets will be disabled too.
     *
     * @uxon-property disabled
     * @uxon-type boolean
     *
     * @see \exface\Core\Widgets\AbstractWidget::setDisabled()
     */
    public function setDisabled($value)
    {
        foreach ($this->getChildren() as $child) {
            $child->setDisabled($value);
        }
        return parent::setDisabled($value);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::countWidgets()
     */
    public function countWidgets(callable $filter = null)
    {
        return count($this->getWidgets($filter));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::countWidgetsVisible()
     */
    public function countWidgetsVisible()
    {
        $count = 0;
        foreach ($this->getWidgets() as $widget) {
            if (! $widget->isHidden()) {
                $count ++;
            }
        }
        return $count;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::findChildrenByAttribute()
     */
    public function findChildrenByAttribute(Attribute $attribute)
    {
        $result = array();
        
        foreach ($this->widgets as $widget) {
            if ($widget instanceof iShowSingleAttribute && $widget->getAttribute() && $widget->getAttribute()->getId() == $attribute->getId()) {
                $result[] = $widget;
            }
        }
        
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::getInputWidgets()
     */
    public function getInputWidgets($depth = null)
    {
        $result = array();
        foreach ($this->getWidgets() as $widget) {
            if (($widget instanceof iTakeInput) && ! $widget->isReadonly()) {
                $result[] = $widget;
            }
            if ($widget instanceof iContainOtherWidgets) {
                if ($depth === 1) {
                    continue;
                }
                $result = array_merge($result, $widget->getInputWidgets($depth ? $depth - 1 : null));
            }
        }
        return $result;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::generate_uxon_object()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $widgets_array = array();
        foreach ($this->getWidgets() as $widget) {
            $widgets_array[] = $widget->exportUxonObject();
        }
        if (count($widgets_array) > 0) {
            $uxon->setProperty('widgets', UxonObject::fromArray($widgets_array));
        }
        return $uxon;
    }
}
?>