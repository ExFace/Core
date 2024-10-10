<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Widgets\iFilterData;
use exface\Core\Interfaces\Widgets\iHaveFilters;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * The configurator for dashboards allows to add global filters, that apply to all widgets inside the dashboard.
 * 
 * @see WidgetConfigurator
 * 
 * @method \exface\Core\Widgets\Dashboard getWidgetConfigured()
 * 
 * @author Andrej Kabachnik
 *        
 */
class DashboardConfigurator extends WidgetConfigurator implements iHaveFilters
{    
    private $filter_tab = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFilters()
     */
    public function getFilters() : array
    {
        return $this->getFilterTab()->getWidgets();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::getFiltersApplied()
     */
    public function getFiltersApplied() : array
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
     * Defines filters to be used in this configurator.
     *  
     * @uxon-property filters
     * @uxon-type exface\Core\Widgets\Filter[]
     * @uxon-template [{"attribute_alias": ""}]
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::setFilters()
     */
    public function setFilters(UxonObject $uxon_objects) : iHaveFilters
    {
        foreach ($uxon_objects as $uxon) {
            $filter = $this->createFilter($uxon);
            $this->addFilter($filter);
        }
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
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::addFilter()
     */
    public function addFilter(WidgetInterface $filter_widget) : iHaveFilters
    {        
        if (! ($filter_widget instanceof iFilterData)) {
            throw new WidgetConfigurationError($this, 'Cannot use "' . $filter_widget->getWidgetType() . '" as filter in a dashboard!');
        }
        $this->getFilterTab()->addWidget($filter_widget);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFilters::hasFilters()
     */
    public function hasFilters() : bool
    {
        return $this->getFilterTab()->hasWidgets();
    }
    
    /**
     * Returns the configurator tab with filters.
     * 
     * @return Tab
     */
    public function getFilterTab() : Tab
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
    protected function createFilterTab() : Tab
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.FILTER_TAB_CAPTION'), false);
        $tab->setIcon(Icons::FILTER);
        return $tab;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter_callback = null)
    {
        if (null === $this->filter_tab){
            $this->getFilterTab();
        }
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * Returns the Dashboard widget, that this configurator is bound to.
     * 
     * This is similar to getWidgetConfigured(), but the latter may also return other widget types
     * (e.g. those using data like charts or diagrams), whild getDataWidget() allways returns the
     * data widget itself.
     * 
     * @return Dashboard
     */
    public function getDashboardWidget() : Data
    {
        return $this->getWidgetConfigured();
    }
}