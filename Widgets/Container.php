<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetChildNotFoundError;
use exface\Core\Exceptions\UnderflowException;
use exface\Core\Interfaces\Widgets\iCanPreloadData;
use exface\Core\Widgets\Traits\iCanPreloadDataTrait;

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
class Container extends AbstractWidget implements iContainOtherWidgets, iCanPreloadData
{
    use iCanPreloadDataTrait;
    
    private $widgets = array();
    
    private $readonly = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::doPrefill()
     */
    protected function doPrefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet)
    {
        foreach ($this->getChildren() as $widget) {
            $widget->prefill($data_sheet);
        }
        
        return;
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
        
        foreach ($this->getChildren() as $widget) {
            $data_sheet = $widget->prepareDataSheetToRead($data_sheet);
        }
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
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
    public function getChildren() : \Iterator
    {
        foreach ($this->getWidgets() as $child) {
            yield $child;
        }
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
        if (! is_null($filter)){
            return array_values(array_filter($this->widgets, $filter));
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
     * @uxon-type \exface\Core\Widgets\AbstractWidget[]
     * @uxon-template [{"": ""}]
     *
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::setWidgets()
     */
    public function setWidgets($widget_or_uxon_array)
    {
        $readonly = $this->isReadonly();
        foreach ($widget_or_uxon_array as $w) {
            if ($w instanceof WidgetInterface) {
                $this->addWidget($w);
            } else {
                $page = $this->getPage();
                $widget = WidgetFactory::createFromUxon($page, $w, $this, null, $readonly);
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
            if ($widget->isHidden() === false) {
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
    public function findChildrenByAttribute(MetaAttributeInterface $attribute)
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iContainOtherWidgets::findChildrenRecursive()
     */
    public function findChildrenRecursive(callable $filterCallback, $maxDepth = null) : array
    {
        $result = [];
        foreach ($this->getChildren() as $child) {
            if (call_user_func($filterCallback, $child) === true) {
                $result[] = $child;
            }
            if (($maxDepth === null || $maxDepth > 0) && $child->hasChildren()) {
                $result = array_merge($result, $child->findChildrenRecursive($filterCallback, ($maxDepth !== null ? $maxDepth-1 : null)));
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
        
        // Remove any widget definitions, that might come from the original uxon.
        $uxon->setProperty('widgets', new UxonObject());
        // Now add the exported uxons from the current container contents.
        foreach ($this->getWidgets() as $widget) {
            $uxon->appendToProperty('widgets', $widget->exportUxonObject());
        }
        
        return $uxon;
    }
    
    /**
     * Controls if the default widget for attributes in sub-widgets is an editor (FALSE) or a display (TRUE).
     * 
     * Many container widgets will have sub-widgets with only the `attribute_alias` defined: in
     * this case, the most appropriate widget for the attribute will be used automatically -
     * in general, the system will use either the default editor or the default display widget
     * of the attribute. By setting the `readonly` property on a container, you can influence this
     * decision and force the use of editors (setting `readonly` to `true`) or display widget (`false`).
     * 
     * This property only affects the type of widget chosen for attribute-based widgets without
     * an explicitly specified `widget_type`. This means, if you have a readonly `Panel`, you can
     * still add widgets of type `Input` and they will be rendered as regular inputs. This is
     * particularly usefull to create non-editable overviews with a view `InputHiddens` holding the
     * desired input data for actions.
     * 
     * If not set explicitly, this property will be inherited from parent containers or assumed
     * `false` if no parent containers exist.
     * 
     * @uxon-property readonly
     * @uxon-type boolean
     * 
     * @return bool
     */
    public function isReadonly() : bool
    {
        if ($this->readonly === null) {
            if ($this->hasParent() === true && $this->getParent() instanceof Container) {
                return $this->getParent()->isReadonly();
            } else {
                return false;
            }
        }
        return $this->readonly;
    }
    
    /**
     * 
     * @param bool $value
     * @return Container
     */
    public function setReadonly($value) : WidgetInterface
    {
        $this->readonly = $value;
        return $this;
    }
}
?>