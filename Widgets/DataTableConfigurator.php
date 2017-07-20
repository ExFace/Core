<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;

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
 * @author Andrej Kabachnik
 *
 */
class DataTableConfigurator extends DataConfigurator
{
    private $column_tab = null;
    
    private $aggregation_tab = null;
    
    /**
     * 
     * @return Tab
     */
    public function getColumnTab()
    {
        if (is_null($this->column_tab)){
            $this->column_tab = $this->createColumnTab();
            $this->addTab($this->column_tab, 3);
        }
        return $this->column_tab;
    }
    
    /**
     * 
     * @return Tab
     */
    protected function createColumnTab()
    {
        $tab = $this->createTab();
        $tab->setCaption($this->translate('WIDGET.DATACONFIGURATOR.COLUMN_TAB_CAPTION'));
        $tab->setIconName(Icons::TABLE);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
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
        $tab->setIconName(Icons::OBJECT_GROUP);
        // TODO reenable the tab once it has content
        $tab->setDisabled(true);
        return $tab;
    }
    
}