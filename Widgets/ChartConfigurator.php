<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * A configurator widget for charts with filters, sorters and chart-specific options.
 * 
 * The `ChartConfigurator` is built on-top of the configurator for the data widget of
 * the `Chart`.
 * 
 * @author Andrej Kabachnik
 * 
 * @method Chart getWidgetConfigured()
 *
 */
class ChartConfigurator extends DataConfigurator
{    
    /**
     * @var DataConfigurator $dataConfigurator
     */
    private $dataConfigurator = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getDataWidget()
     */
    public function getDataWidget() : Data
    {
        return $this->getWidgetConfigured()->getData();
    }
    
    public function setDataConfigurator(DataConfigurator $widget) : ChartConfigurator
    {
        $this->dataConfigurator = $widget;
        return $this;
    }
    
    /**
     *
     * @return DataConfigurator
     */
    protected function getDataConfigurator() : DataConfigurator
    {
        if( $this->dataConfigurator === null) {
            $this->dataConfigurator = $this->getDataWidget()->getConfiguratorWidget();
        }
        return $this->dataConfigurator;
    }
    
    /**
    * Returns an array with all filter widgets.
    *
    * @return Filter[]
    */
    public function getFilters()
    {
        return $this->getDataConfigurator()->getFilters();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::setFilters()
     */
    public function setFilters(UxonObject $uxon_objects)
    {
        $this->getDataConfigurator()->setFilters($uxon_objects);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::addFilter()
     */
    public function addFilter(AbstractWidget $filter_widget, $include_in_quick_search = false)
    {
        $this->getDataConfigurator()->addFilter($filter_widget, $include_in_quick_search);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getFilterTab()
     */
    public function getFilterTab()
    {
        return $this->getDataConfigurator()->getFilterTab();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getSorterTab()
     */
    public function getSorterTab()
    {
        return $this->getDataConfigurator()->getSorterTab();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::addQuickSearchFilter()
     */
    public function addQuickSearchFilter(Filter $widget) : self
    {
        $this->getDataConfigurator()->addQuickSearchFilter($widget);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getQuickSearchFilters()
     */
    public function getQuickSearchFilters()
    {
        return $this->getDataConfigurator()->getQuickSearchFilters();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::setQuickSearchFilters()
     */
    public function setQuickSearchFilters(array $filters)
    {
        $this->getDataConfigurator()->setQuickSearchFilters($filters);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::setLazyLoading()
     */
    public function setLazyLoading(bool $value)
    {
        $this->getDataConfigurator()->setLazyLoading($value);
        return $this;
    }
}