<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\WidgetVisibilityDataType;

/**
 * The configurator for data widgets contains tabs for filters and sorters.
 * 
 * @see WidgetConfigurator
 * 
 * @method Data getWidgetConfigured()
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataConfigurator extends WidgetConfigurator implements iHaveFilters
{    
    private $filter_tab = null;
    
    private $sorter_tab = null;
    
    /**
     * Returns an array with all filter widgets.
     *
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->getFilterTab()->getWidgets();
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
        foreach ($this->getFilters() as $id => $fltr) {
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
     * Note, that InputComboTable widgets will be automatically generated for related objects if the corresponding
     * filter is defined by the attribute, representing the relation: e.g. for a table of ORDER_POSITIONS,
     * adding the filter ORDER (relation to the order) will give you a InputComboTable, while the filter ORDER__NUMBER
     * will yield a numeric input field, because it filter over a number, even thoug a related one.
     *
     * Advanced users can also instantiate a Filter widget manually (widget_type = Filter) gaining control
     * over comparators, etc. The widget displayed can then be defined in the widget-property of the Filter.
     *
     * A good way to start is to copy the columns array and rename it to filters. This will give you filters
     * for all columns.
     *
     * Example:
     *  {
     *      "object_alias": "ORDER_POSITION"
     *      "filters": [
     *          {
     *              "attribute_alias": "ORDER"
     *          },
     *          {
     *              "attribute_alias": "CUSTOMER__CLASS"
     *          },
     *          {
     *              "attribute_alias": "ORDER__ORDER_POSITION__VALUE:SUM",
     *              "caption": "Order total"
     *          },
     *          {
     *              "attribute_alias": "VALUE",
     *              "widget_type": "InputNumberSlider"
     *          }
     *      ]
     *  }
     *  
     * @uxon-property filters
     * @uxon-type exface\Core\Widgets\Filter[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @param UxonObject[] $uxon_objects
     * @return DataConfigurator
     */
    public function setFilters(UxonObject $uxon_objects)
    {
        foreach ($uxon_objects as $uxon) {
            $filter = $this->createFilterWidget($uxon->getProperty('attribute_alias'), $uxon);
            $this->addFilter($filter);
        }
        return $this;
    }
    
    /**
     * Creates a `Filter` widget from the given attribute alias an/or a UXON description of a `Filter` or `Input` widget.
     * 
     * NOTE: the filter is NOT added to the filters-tab automatically!!!
     * 
     * @param string $attribute_alias
     * @param UxonObject $uxon_object
     * @return Filter
     */
    public function createFilterWidget(string $attribute_alias = null, UxonObject $uxon_object = null) : Filter
    {
        if ($uxon_object !== null) {
            // Legacy filters (before November 2019) allowed to mix filter properties and input 
            // widget properties within the filter and there was no nested input_widget in UXON.
            // At that time, there was only the default `Filter`.
            $mergedWidget = false;
            // If we have a widget type, that is not a filter, it must be a legacy filter widget
            if ($wType = $uxon_object->getProperty('widget_type')) {
                if (is_a(WidgetFactory::getWidgetClassFromType($wType), '\\' . Filter::class, true) === false) {
                    $mergedWidget = true;
                }
            } elseif ($uxon_object->hasProperty('id') === true) {
                // If there is an id, it is very probable, that it's a legacy filter. It is important
                // to give that id to the input widget and not the filter as live references are
                // typically intended to point to the input.
                $mergedWidget = true;
            }
            
            // If a merged widget is detected, we need to extract attributes, that the Filter had 
            // at that time from the UXON and treat everything else as input widget properties
            if ($mergedWidget === true) {
                $inputUxon = $uxon_object->copy();
                $uxon_object = new UxonObject();
                // Set properties of the filter explicitly while passing everything else to it's input widget.
                if ($inputUxon->hasProperty('comparator')) {
                    $comparator = $inputUxon->getProperty('comparator');
                    $uxon_object->setProperty('comparator', $comparator);
                    $inputUxon->unsetProperty('comparator');
                }
                if ($inputUxon->hasProperty('apply_on_change')) {
                    $uxon_object->setProperty('apply_on_change', $inputUxon->getProperty('apply_on_change'));
                    $inputUxon->unsetProperty('apply_on_change');
                }
                if ($inputUxon->hasProperty('condition_group')) {
                    $uxon_object->setProperty('condition_group', $inputUxon->getProperty('condition_group'));
                    $inputUxon->unsetProperty('condition_group');
                }
                if ($uxon_object !== null && $inputUxon->hasProperty('required')) {
                    // A filter is only required, if a user explicitly marked it as required in the filter's UXON
                    $uxon_object->setProperty('required', $inputUxon->getProperty('required'));
                    $inputUxon->unsetProperty('required');
                } 
                
                $uxon_object->setProperty('input_widget', $inputUxon);
            }
        }
        
        if ($uxon_object === null) {
            $uxon_object = new UxonObject();
        }
        
        if ($attribute_alias !== null) {
            $uxon_object->setProperty('attribute_alias', $attribute_alias);
        }
        
        return WidgetFactory::createFromUxonInParent($this->getFilterTab(), $uxon_object, 'Filter');
    }
    
    /**
     * Adds a widget as a filter.
     * Any widget, that can be used to input a value, can be used for filtering. It will automatically be wrapped in a filter
     * widget. The second parameter (if set to TRUE) will make the filter automatically get used in quick search queries.
     *
     * @param AbstractWidget $filter_widget
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::addFilter()
     */
    public function addFilter(AbstractWidget $filter_widget)
    {
        if ($filter_widget instanceof Filter) {
            $filter = $filter_widget;
        } else {
            $filter = WidgetFactory::create($this->getPage(), 'Filter', $this->getFilterTab());
            $filter->setInputWidget($filter_widget);
        }
        
        $this->setLazyLoadingForFilter($filter);
        
        $this->getFilterTab()->addWidget($filter);
        return $this;
    }
    
    public function hasFilters()
    {
        return $this->getFilterTab()->hasWidgets();
    }
    
    /**
     * Returns the configurator tab with filters.
     * 
     * @return Tab
     */
    public function getFilterTab()
    {
        if (is_null($this->filter_tab)){
            $this->filter_tab = $this->createFilterTab();
            $this->addTab($this->filter_tab, 0);
        }
        return $this->filter_tab;
    }
    
    /**
     * Creates an empty filter tab and returns it (without adding to the Tabs widget!)
     * 
     * @return Tab
     */
    protected function createFilterTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.FILTER_TAB_CAPTION'), false);
        $tab->setIcon(Icons::FILTER);
        return $tab;
    }
    
    /**
     * Returns the configurator tab with sorting controls
     * 
     * @return Tab
     */
    public function getSorterTab()
    {
        if (is_null($this->sorter_tab)){
            $this->sorter_tab = $this->createSorterTab();
            $this->addTab($this->sorter_tab, 1);
        }
        return $this->sorter_tab;
    }
    
    /**
     * Creates an empty sorter tab and returns it (without adding to the Tabs widget!)
     *
     * @return Tab
     */
    protected function createSorterTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.SORTER_TAB_CAPTION'), false);
        $tab->setIcon(Icons::SORT);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }
    
    /**
     * 
     * @return Filter[]
     */
    public function getQuickSearchFilters() : array
    {
        $filters = [];
        foreach ($this->getFilters() as $filter){
            if ($filter->getIncludeInQuickSearch() === true) {
                $filters[] = $filter;
            }
        }
        return $filters;
    }
    
    /**
     * Returns an array of filters, that filter over the given attribute.
     * 
     * It will mostly contain only one filter, but if there are different filters with different 
     * comparators (like from+to for numeric or data values), there will be multiple filters in 
     * the list. 
     *
     * @param MetaAttributeInterface $attribute
     * @return Filter[]
     */
    public function findFiltersByAttribute(MetaAttributeInterface $attribute)
    {
        $result = array();
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->getAttributeAlias() == $attribute->getAliasWithRelationPath()) {
                $result[] = $filter_widget;
            } 
        }
        return $result;
    }
    
    /**
     * TODO Make the method return an array like find_filters_by_attribute() does
     *
     * @param MetaRelationInterface $relation
     * @return Filter
     */
    public function findFilterByRelation(MetaRelationInterface $relation)
    {
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->isBoundToAttribute() === true && $filter_widget->getAttribute()->is($relation->getLeftKeyAttribute())) {
                $found = $filter_widget;
                break;
            } else {
                $found = null;
            }
        }
        if ($found) {
            return $found;
        } else {
            return false;
        }
    }
    
    /**
     * Returns the first filter based on the given object or it's attributes
     *
     * @param MetaObjectInterface $object
     * @return \exface\Core\Widgets\Filter|boolean
     */
    public function findFiltersByObject(MetaObjectInterface $object)
    {
        $result = array();
        foreach ($this->getFilters() as $filter_widget) {
            if ($filter_widget->isBoundToAttribute() === false) {
                continue;
            }
            try {
                $filter_attr = $this->getMetaObject()->getAttribute($filter_widget->getAttributeAlias());
            } catch (MetaAttributeNotFoundError $e) {
                throw new WidgetConfigurationError($filter_widget, 'Filter attribute "' . $filter_widget->getAttributeAlias() . '" not found for object "' . $this->getMetaObject()->getName() . '" (' . $this->getMetaObject()->getAliasWithNamespace() . ')!', null, $e);
            }
            $filter_object = $filter_attr->getObject();
            if ($object->is($filter_object)) {
                $result[] = $filter_widget;
            } elseif ($filter_widget->getAttribute()->isRelation() && $object->is($filter_widget->getAttribute()->getRelation()->getRightObject())) {
                $result[] = $filter_widget;
            }
        }
        return $result;
    }
    
    /**
     * Adds a new filter over the given relation if it does not exist yet and returns it.
     * 
     * NOTE: by default this method will create a simple `Input` widget (or `InputHidden` for hidden relation 
     * attributes) and not use the default editor configuration To revert to using the default editor of the 
     * attribute for the filter, set the second parameter to TRUE.
     * 
     * Using the default editor like in regular filter had repeatedly caused issues because people would simply 
     * forget, that the filter does not really exist and would start counting ot it or suspect a bug when not
     * founding the filter when attempting to customize it. For AJAX-autosuggests, the AJAX requests also failed 
     * because the filter only exists when the configurator is prefilled (and not on a read-request for a possible 
     * autosuggest or so). Also adding "normal" filters automatically does not draw the user's attention, while 
     * adding a (mostly uncomfortable) simple input will usually require additional customizing leading to a 
     * properly added filter.
     *
     * @param MetaRelationInterface $relation
     * @param bool $useDefaultEditorOfAttribute
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function addFilterFromRelation(MetaRelationInterface $relation, bool $useDefaultEditorOfAttribute = false) : Filter
    {
        $filter_widget = $this->findFilterByRelation($relation);
        // Create a new hidden filter if there is no such filter already
        if (! $filter_widget) {
            $page = $this->getPage();
            $filter_attr = $relation->getLeftKeyAttribute();
            if ($useDefaultEditorOfAttribute === true) {
                $filter_uxon = $filter_attr->getDefaultEditorUxon();
                $filter_uxon->setProperty('attribute_alias', $filter_attr->getAliasWithRelationPath());
                if ($filter_attr->isHidden() === true && $filter_uxon->hasProperty('visibility') === false) {
                    $filter_uxon->setProperty('visibility', WidgetVisibilityDataType::HIDDEN);
                }
            } else {
                $filter_uxon = new UxonObject([
                    'attribute_alias' => $filter_attr->getAliasWithRelationPath(),
                    'widget_type' => 'Filter',
                    'comparator' => ComparatorDataType::EQUALS,
                    'input_widget' => [
                        'widget_type' => $filter_attr->isHidden() ? 'InputHidden' : 'Input'
                    ]
                ]);
            }
            $filter_widget = WidgetFactory::createFromUxon($page, $filter_uxon, $this->getFilterTab());
            $this->addFilter($filter_widget);
        }
        return $filter_widget;
    }
    
    protected function setLazyLoadingForFilter(Filter $filter_widget)
    {
        // Disable filters on Relations if lazy loading is disabled
        if (! $this->getWidgetConfigured()->getLazyLoading() && $filter_widget->getAttribute() && $filter_widget->getAttribute()->isRelation() && $filter_widget->getInputWidget()->is('InputComboTable')) {
            $filter_widget->setDisabled(true);
        }
        return $filter_widget;
    }
    
    public function setLazyLoading(bool $value)
    {
        foreach ($this->getFilters() as $filter) {
            $this->setLazyLoadingForFilter($filter);
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if (is_null($this->filter_tab)){
            $this->getFilterTab();
        }
        if (is_null($this->sorter_tab)){
            $this->getSorterTab();
        }
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * Returns the data widget, that this configurator is bound to.
     * 
     * This is similar to getWidgetConfigured(), but the latter may also return other widget types
     * (e.g. those using data like charts or diagrams), whild getDataWidget() allways returns the
     * data widget itself.
     * 
     * @return Data
     */
    public function getDataWidget() : Data
    {
        return $this->getWidgetConfigured();
    }
}
?>