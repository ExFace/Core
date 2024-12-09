<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveColumns;

/**
 * DataTable-configurators contain tabs for filters, sorters and column controls.
 * 
 * In addition to the basic DataConfigurator which can be applied to any Data
 * widget, the DataTableConfigurator has a tab to control the order and visibility
 * of table columns.
 * 
 * TODO the table column control tab is not available yet
 * TODO the aggregations control tab is not available yet
 * 
 * @author Andrej Kabachnik, Georg Bieger
 * 
 * @method \exface\Core\Widgets\DataTable getWidgetConfigured()
 *
 */
class DataTableConfigurator extends DataConfigurator
{
    private $column_tab = null;
    
    private $aggregation_tab = null;

    public function getWidgets(callable $filter_callback = null): array
    {
        // Make sure to initialize the columns tab. This will automatically add
        // it to the default widget array inside the container.
        if (null === $this->column_tab){
            $this->getColumnsTab();
        }
        // TODO add aggregation tab once it is functional 
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * 
     * @return Tab
     */
    public function getColumnsTab()
    {
        if (is_null($this->column_tab)){
            $this->column_tab = $this->createColumnsTab();
            $this->addTab($this->column_tab, 3);
        }
        return $this->column_tab;
    }

    /**
     * 
     * @return Tab
     */
    protected function createColumnsTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.COLUMN_TAB_CAPTION'));
        $tab->setIcon(Icons::TABLE);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }

    public function addColumn(DataColumn $column) : DataTableConfigurator
    {
        $this->getColumnsTab()->addWidget($column);
        $column->setParent($this->getWidgetConfigured());
        return $this;
    }

    public function createColumnFromUxon(UxonObject $uxon) : DataColumn
    {
        return $this->getWidgetConfigured()->createColumnFromUxon($uxon);
    }
    
    /**
     * 
     * @return Tab
     */
    public function getAggregationTab()
    {
        if (is_null($this->aggregation_tab)){
            $this->aggregation_tab = $this->createAggregationTab();
            $this->addTab($this->aggregation_tab, 4);
        }
        return $this->column_tab;
    }
    
    /**
     *
     * @return Tab
     */
    protected function createAggregationTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.AGGREGATION_TAB_CAPTION'));
        $tab->setIcon(Icons::OBJECT_GROUP);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }
    
}