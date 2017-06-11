<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTableConfigurator extends WidgetConfigurator implements iHaveFilters
{
    
    /** @var Filter[] */
    private $filters = array();
    
    /** @var Filter[] */
    private $quick_search_filters = array();
    
    /**
     * Returns an array with all filter widgets.
     *
     * @return Filter[]
     */
    public function getFilters()
    {
        if (count($this->filters) == 0) {
            $this->addRequiredFilters();
        }
        return $this->filters;
    }
    
    /**
     * Returns the filter widget matching the given widget id
     *
     * @param string $filter_widget_id
     * @return \exface\Core\Widgets\Filter
     */
    public function getFilter($filter_widget_id)
    {
        foreach ($this->getFilters() as $fltr) {
            if ($fltr->getId() == $filter_widget_id) {
                return $fltr;
            }
        }
    }
    
    /**
     * Returns all filters, that have values and thus will be applied to the result
     *
     * @return \exface\Core\Widgets\AbstractWidget[] array of widgets
     */
    public function getFiltersApplied()
    {
        $result = array();
        foreach ($this->filters as $id => $fltr) {
            if (! is_null($fltr->getValue())) {
                $result[$id] = $fltr;
            }
        }
        return $result;
    }
    
    /**
     * Defines filters to be used in this data widget: each being a Filter widget.
     *
     * The simples filter only needs to contain an attribute_alias. ExFace will generate a suitable widget
     * automatically. However, the filter can easily be customized by adding any properties applicable to
     * the respective widget type. You can also override the widget type.
     *
     * Relations and aggregations are fully supported by filters
     *
     * Note, that ComboTable widgets will be automatically generated for related objects if the corresponding
     * filter is defined by the attribute, representing the relation: e.g. for a table of ORDER_POSITIONS,
     * adding the filter ORDER (relation to the order) will give you a ComboTable, while the filter ORDER__NUMBER
     * will yield a numeric input field, because it filter over a number, even thoug a related one.
     *
     * Advanced users can also instantiate a Filter widget manually (widget_type = Filter) gaining control
     * over comparators, etc. The widget displayed can then be defined in the widget-property of the Filter.
     *
     * A good way to start is to copy the columns array and rename it to filters. This will give you filters
     * for all columns.
     *
     * Example:
     * "object_alias": "ORDER_POSITION"
     * "filters": [
     * {
     * "attribute_alias": "ORDER"
     * },
     * {
     * "attribute_alias": "CUSTOMER__CLASS"
     * },
     * {
     * "attribute_alias": "ORDER__ORDER_POSITION__VALUE:SUM",
     * "caption": "Order total"
     * },
     * {
     * "attribute_alias": "VALUE",
     * "widget_type": "InputNumberSlider"
     * }
     * ]
     *
     * @uxon-property filters
     * @uxon-type Filter[]
     *
     * @param array $filters_array
     * @return boolean
     */
    public function setFilters(array $filters_array)
    {
        if (! is_array($filters_array))
            return false;
            foreach ($filters_array as $f) {
                $include_in_quick_search = false;
                // Add to quick search if required
                if ($f->include_in_quick_search === true) {
                    $include_in_quick_search = true;
                }
                unset($f->include_in_quick_search);
                
                $filter = $this->createFilterWidget($f->attribute_alias, $f);
                $this->addFilter($filter, $include_in_quick_search);
            }
            $this->addRequiredFilters();
            return true;
    }
    
    public function createFilterWidget($attribute_alias = null, \stdClass $uxon_object = null)
    {
        if (is_null($attribute_alias)) {
            if ($uxon_object->attribute_alias) {
                $attribute_alias = $uxon_object->attribute_alias;
            } elseif ($uxon_object->widget && $uxon_object->widget->attribute_alias) {
                $attribute_alias = $uxon_object->widget->attribute_alias;
            }
        }
        // a filter can only be applied, if the attribute alias is specified and the attribute exists
        if (! $attribute_alias)
            throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for an empty attribute alias in widget "' . $this->getId() . '"!', '6T91AR9');
            try {
                $attr = $this->getMetaObject()->getAttribute($attribute_alias);
            } catch (MetaAttributeNotFoundError $e) {
                throw new WidgetPropertyInvalidValueError($this, 'Cannot create a filter for attribute alias "' . $attribute_alias . '" in widget "' . $this->getId() . '": attribute not found for object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', '6T91AR9', $e);
            }
            // determine the widget for the filter
            $uxon = $attr->getDefaultWidgetUxon()->copy();
            if ($uxon_object) {
                $uxon = $uxon->extend(UxonObject::fromStdClass($uxon_object));
            }
            // Set a special caption for filters on relations, which is derived from the relation itself
            // IDEA this might be obsolete since it probably allways returns the attribute name anyway, but I'm not sure
            if (! $uxon->hasProperty('caption') && $attr->isRelation()) {
                $uxon->setProperty('caption', $this->getMetaObject()->getRelation($attribute_alias)->getName());
            }
            $page = $this->getPage();
            if ($uxon->comparator) {
                $comparator = $uxon->comparator;
                unset($uxon->comparator);
            }
            
            $filter = $this->getPage()->createWidget('Filter', $this);
            $filter->setComparator($comparator);
            $filter->setWidget(WidgetFactory::createFromUxon($page, $uxon, $filter));
            
            return $filter;
    }
    
}
?>