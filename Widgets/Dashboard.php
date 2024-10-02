<?php
namespace exface\Core\Widgets;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFilterData;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Widgets\Parts\DataFilterMapping;
use exface\Core\Widgets\Traits\iHaveConfiguratorTrait;

/**
 * A special grid-widget for building dashboards.
 * 
 * A dashboard contains a common header with filters and a set of inner widgets, that
 * these filters control. Depending on these inner widgets, the dashboard can follow
 * different layout principles.
 * 
 * ## Split layout 
 * 
 * A split-style dashboard contains only one widget: a `SplitHorizontal` or `SplitVertical`.
 * In this case, the dashboard screen consists of bordered areas and is not scrollable.
 * It is the same as a split widget, but with the option to have have common filters.
 * 
 * ## Grid layout 
 * 
 * A grid dashboard is generated in all other cases - that is, if you just place non-split 
 * widgets in the dashboard. Such a dashboard will display widgets as cards in a masonry-style 
 * grid. Typical child elements of grid dashboards are `DataTable`, `Chart` or `Card`. Use `Card` 
 * widgets to group smaller widgets within a dashboard.
 * 
 * ## Common filters
 * 
 * A dasboard can provide common filters, that apply to all widgets in it. It has its own
 * `DashboardConfigurator` to house these filters. For now, this configurator only supports
 * filters. In practice, it is easier to define `filters` directly in the dashboard.
 * 
 * E.g. if you have an `APP` filter on `exface.Core.PAGE`, you can also apply it to 
 * `exface.Core.PAGE_GROUP`, but in that case it would not be `APP`, but rather 
 * `PAGE__APP`. This widget part allows to define these mappings.
 * 
 * Example:
 * 
 * ```json
 *  {
 *      "object_alias": "exface.Core.PAGE",
 *      "widget_type": "Dashbaord",
 *      "filters": [
 *          {"attribtue_alias": "APP"}
 *      ],
 *      "filters_apply_to": {
 *          "APP": [
 *              {
 *                  "object_alias": "exface.Core.PAGE_GROUP",
 *                  "filter": {
 *                      "attribute_alias": "PAGE__APP",
 *                      "required": true
 *                  }
 *              },
 *              {
 *                  "object_alias": "exface.Core.OBJECT",
 *                  "disabled": true,
 *                  "filter": {
 *                      "attribute_alias": "APP"
 *                  }
 *              }
 *          ]
 *      }
 *  }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *        
 */
class Dashboard extends WidgetGrid implements iHaveConfigurator, iHaveFilters
{
    use iHaveConfiguratorTrait;

    const LAYOUT_GRID = 'grid';

    const LAYOUT_SPLIT = 'split';

    private $filtersApplyTo = [];

    private $filtersAppliedHidden = true;

    private $filtersApplyAttributesWithSameAlias = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    public function getConfiguratorWidgetType() : string
    {
        return 'DashboardConfigurator';
    } 

    /**
     * 
     * @return string
     */
    public function getLayoutType() : string
    {
        if ($this->getWidgetFirst() instanceof Split) {
            return self::LAYOUT_SPLIT;
        }
        return self::LAYOUT_GRID;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFiltersApplied()
     */
    public function getFiltersApplied() : array
    {
        return $this->getConfiguratorWidget()->getFiltersApplied();
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::hasFilters()
     */
    public function hasFilters() : bool
    {
        return $this->getConfiguratorWidget()->hasFilters();
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFilters()
     */
    public function getFilters() : array
    {
        return $this->getConfiguratorWidget()->getFilters();
    }
    
    /**
     * Common filters for the entire dashboard
     * 
     * @uxon-property filters
     * @uxon-type exface\Core\Widgets\Filter[]
     * @uxon-template [{"attribute_alias": ""}]
     */
    public function setFilters(UxonObject $uxon_objects) : iHaveFilters
    {
        $this->getConfiguratorWidget()->setFilters($uxon_objects);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::addFilter()
     */
    public function addFilter(WidgetInterface $filter_widget) : iHaveFilters
    {
        $this->getConfiguratorWidget()->addFilter($filter_widget);
        return $this;
    }
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::createFilter()
     */
    public function createFilter(UxonObject $uxon = null) : iFilterData
    {
        return WidgetFactory::createFromUxonInParent($this->getFilterTab(), $uxon, 'Filter');
    }

    public function getChildren() : \Iterator
    {
        yield from parent::getChildren();
        yield $this->getConfiguratorWidget();
    }

    /**
     * Map filters of the dashboard to filters of child widgets, so that they automatically get the value of the dashboard filter
     * 
     * @uxon-property filters_apply_to
     * @uxon-type object
     * @uxon-template {"// dashboard filter attribute alias": [{"object_alias": "", "filter": {"attribute_alias": ""}}]}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return \exface\Core\Widgets\Dashboard
     */
    protected function setFiltersApplyTo(UxonObject $uxon) : Dashboard
    {
        foreach ($uxon as $filterAttributeAlias => $mappings) {
            foreach ($mappings as $mapUxon) {
                $part = new DataFilterMapping($this, $filterAttributeAlias, $mapUxon);
            }
        }
        $this->filtersApplyTo[$filterAttributeAlias][] = $part;
        return $this;
    }

    /**
     * 
     * @return DataFilterMapping[][]
     */
    protected function getFiltersApplyTo() : array
    {
        return $this->filtersApplyTo;
    }

    /**
     * Set to TRUE to automatically apply dashboard filters to any child widget object whereever attribute aliases match
     * 
     * @uxon-property filters_apply_to_attributes_with_matching_aliases
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return \exface\Core\Widgets\Dashboard
     */
    protected function setFiltersApplyToAttributesWithMatchingAliases(bool $trueOrFalse) : Dashboard
    {
        $this->filtersApplyAttributesWithSameAlias = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function getFiltersApplyToUxonAttributesWithMatchingAliases() : bool
    {
        return $this->filtersApplyAttributesWithSameAlias ?? false;
    }

    /**
     * Registers filters and value links on all data-related widgets in the dashboard to connect them to the dashboard filters
     * 
     * @return \exface\Core\Widgets\Dashboard
     */
    public function registerFilterLinks() : Dashboard
    {
        $filtersToAppy = $this->getFilters();

        if (empty($filtersToAppy)) {
            return $this;
        }

        // Find children, that have filters
        $filterableChildren = [];
        foreach ($this->getWidgetsRecursive() as $child) {
            // Find children with filters
            if ($child instanceof iUseData) {
                $child = $child->getData();
            }
            if ($child instanceof iHaveFilters) {
                $filterableChildren[] = $child;
            }
        }

        foreach ($filtersToAppy as $i => $filter) {
            $filterLinkValue = '=' . $filter->getId();
            
            foreach ($filterableChildren as $child) {
                $filterApplied = false;
                // Try to find existing filters in the child object, that can be linked to the dashboard filter
                foreach ($child->getFilters() as $childFilter) {
                    $mapper = $this->getFilterForForeignObject($filter->getAttributeAlias(), $childFilter->getMetaObject());
                    switch (true) {
                        case $mapper !== null && $mapper->isDisabled():
                            $filterApplied = true;
                            break 2;
                        // If filters are to be applied to matching attribute aliases and the alias matches, link the filter
                        case $this->getFiltersApplyToUxonAttributesWithMatchingAliases() && $childFilter->getAttributeAlias() === $filter->getAttributeAlias():
                            $childFilter->setValue($filterLinkValue);
                            $filterApplied = true;
                            break;
                        // If existing filter has the same object, attribute_alias and comparator, link it
                        case $childFilter->getMetaObject()->is($filter->getMetaObject()) && $childFilter->getAttributeAlias() === $filter->getAttributeAlias():
                            if ($childFilter->getValueWidgetLink() === null) {
                                $childFilter->setValue($filterLinkValue);
                                $filterApplied = true;
                            }
                            break;
                        // If there is a foreign filter mapping with the same alias as the found filter, link the filter
                        case $mapper !== null:
                            if ($childFilter->getAttributeAlias() === $mapper->getSourceFilterAttributeAlias()) {
                                $childFilter->setValue($filterLinkValue);
                                $filterApplied = true;
                            }
                            break;
                    }
                }

                // If no existing filter was found, create one
                if (! $filterApplied) {
                    foreach ($filterableChildren as $child) {
                        $uxonTpl = null;
                        $mapper = $this->getFilterForForeignObject($filter->getAttributeAlias(), $child->getMetaObject());
                        if ($mapper !== null) {
                            $uxonTpl = $mapper->getTargetFilterUxon();
                        } else {
                            if ($this->getFiltersApplyToUxonAttributesWithMatchingAliases() && $child->getMetaObject()->hasAttribute($filter->getAttributeAlias())) {
                                $uxonTpl = new UxonObject([
                                    'attribute_alias' => $filter->getAttributeAlias()
                                ]);
                                if ($filter->isRequired()) {
                                    $uxonTpl->setProperty('required', true);
                                }
                            }
                        }

                        if ($uxonTpl === null) {
                            continue;
                        }
                        
                        $uxonTpl->setProperty('value', $filterLinkValue);
                        if ($this->getFiltersAppliedHidden()) {
                            $uxonTpl->setProperty('hidden', true);
                        }
                        $child->addFilter($child->createFilter($uxonTpl));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * 
     * @param string $filterAttributeAlias
     * @param \exface\Core\Interfaces\Model\MetaObjectInterface $object
     * @return DataFilterMapping|null
     */
    protected function getFilterForForeignObject(string $filterAttributeAlias, MetaObjectInterface $object) : ?DataFilterMapping
    {
        $mappings = $this->getFiltersApplyTo();
        if (empty($mappings)) {
            return null;
        }
        foreach ($mappings[$filterAttributeAlias] as $map) {
            if ($object->is($map->getTargetObjectAlias())) {
                return $map;
            }
        }
        return null;
    }

    /**
     * Set to FALSE to make the autogenerated filters in children widgets visible (e.g. for debugging)
     * 
     * @uxon-property filters_applied_hidden
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return \exface\Core\Widgets\Dashboard
     */
    public function setFiltersAppliedHidden(bool $trueOrFalse) : Dashboard
    {
        $this->filtersAppliedHidden = $trueOrFalse;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    protected function getFiltersAppliedHidden() : bool
    {
        return $this->filtersAppliedHidden;
    }
}