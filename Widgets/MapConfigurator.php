<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;

/**
 * A configurator widget for charts with filters, sorters and chart-specific options.
 * 
 * The `ChartConfigurator` is built on-top of the configurator for the data widget of
 * the `Chart`. This is important as a chart can use an external data widget via
 * `data_widget_link`, which would have it's own configurator depending on the type
 * of that widget (e.g. a `DataTableConfigurator` if it's a table). Whatever is set
 * in the configurator of the data widget also has effect on the chart and vice versa.
 * 
 * Technically this is achievend by using the `DataConfigurator` within the `ChartConfigurator`:
 * operations like getting or setting filters are simply forwarded to the `DataConfigurator`,
 * regardless of whether it belongs to the internal (invisible) data or a real data widget
 * somewhere outside the chart.
 * 
 * @author Andrej Kabachnik
 * 
 * @method Chart getWidgetConfigured()
 *
 */
class MapConfigurator extends DataConfigurator
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
        return $this->getWidgetConfigured()->getLayers()[0]->getDataWidget();
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
    public function addFilter(AbstractWidget $filter_widget)
    {
        $this->getDataConfigurator()->addFilter($filter_widget);
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
     * @see \exface\Core\Widgets\DataConfigurator::getQuickSearchFilters()
     */
    public function getQuickSearchFilters() : array
    {
        return $this->getDataConfigurator()->getQuickSearchFilters();
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        return array_merge($this->getDataConfigurator()->getWidgets($filter_callback), parent::getWidgets($filter_callback));
    }
}