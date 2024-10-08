<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\Interfaces\Widgets\iShowData;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iUseData;
use exface\Core\Factories\WidgetFactory;

/**
 * A configurator widget for maps combining filters and sorters from all data layers.
 * 
 * @author Andrej Kabachnik
 * 
 * @method Map getWidgetConfigured()
 *
 */
class MapConfigurator extends DataConfigurator
{    
    public function getMap() : Map
    {
        return $this->getWidgetConfigured();
    }
    
    /**
     * 
     * @return DataConfigurator[]
     */
    protected function getLayerConfigurators() : array
    {
        $result = [];
        foreach ($this->getLayerDataWidgets() as $widget) {
            $result[] = $widget->getConfiguratorWidget();
        }
        return $result;
    }
    
    /**
     * 
     * @return iShowData[]
     */
    protected function getLayerDataWidgets() : array
    {
        $result = [];
        foreach ($this->getMap()->getLayers() as $layer) {
            if ($layer instanceof iUseData) {
                $result[] = $layer->getData();
            }
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getDataWidget()
     */
    public function getDataWidget() : Data
    {
        return $this->getLayerDataWidgets()[0] ?? WidgetFactory::create($this->getPage(), 'Data', $this->getParent());
    }
    
    /**
    * Returns an array with all filter widgets.
    *
    * @return Filter[]
    */
    public function getFilters() : array
    {
        $array = [];
        foreach ($this->getMap()->getLayers() as $layer) {
            if (($layer instanceof iUseData) && $layer->getDataWidgetLink() === null) {
                $c = $layer->getData()->getConfiguratorWidget();
                foreach ($c->getFilters() as $filter) {
                    $array[] = $filter;
                }
            }
        }
        return $array;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::setFilters()
     */
    public function setFilters(UxonObject $uxon_objects) : iHaveFilters
    {
        throw new WidgetConfigurationError($this, 'Cannot add filters to a map directly - only to data layers!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::addFilter()
     */
    public function addFilter(WidgetInterface $filter_widget) : iHaveFilters
    {
        throw new WidgetConfigurationError($this, 'Cannot add filters to a map directly - only to data layers!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getQuickSearchFilters()
     */
    public function getQuickSearchFilters() : array
    {
        $array = [];
        foreach ($this->getLayerConfigurators() as $c) {
            foreach ($c->getQuickSearchFilters() as $filter) {
                $array[] = $filter;
            }
        }
        return $array;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::setLazyLoading()
     */
    public function setLazyLoading(bool $value)
    {
        foreach ($this->getLayerConfigurators() as $c) {
            $c->setLazyLoading($value);
        }
        return $this;
    }
    
    protected function createFilterTab()
    {
        $tab = parent::createFilterTab();
        foreach ($this->getFilters() as $filter) {
            $tab->addWidget($filter);
        }
        return $tab;
    }
    
    /**
     * Creates an empty sorter tab and returns it (without adding to the Tabs widget!)
     *
     * @return Tab
     */
    protected function createSorterTab()
    {
        $tab = parent::createSorterTab();
        foreach ($this->getLayerConfigurators() as $c) {
            foreach ($c->getSorterTab()->getWidgets() as $sorter) {
                $tab->addWidget($sorter);
            }
        }
        return $tab;
    }
}