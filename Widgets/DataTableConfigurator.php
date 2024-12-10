<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\WidgetVisibilityDataType;
use exface\Core\Factories\WidgetFactory;
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

    private $columnsUxon = null;
    
    private $aggregation_tab = null;

    private int $columnsDefaultVisibility = WidgetVisibilityDataType::OPTIONAL;

    public function getWidgets(callable $filter_callback = null): array
    {
        // Make sure to initialize the columns tab. This will automatically add
        // it to the default widget array inside the container.
        if (null === $this->column_tab){
            $this->getOptionalColumnsTab();
        }
        // TODO add aggregation tab once it is functional 
        return parent::getWidgets($filter_callback);
    }
    
    /**
     * 
     * @return Tab
     */
    public function getOptionalColumnsTab() : Tab
    {
        if (null === $this->column_tab){
            $this->column_tab = $this->createColumnsTab();
            $this->addTab($this->column_tab, 3);
            $this->initColumns();
        }
        return $this->column_tab;
    }

    public function getOptionalColumns() : array
    {
        return $this->getOptionalColumnsTab()->getWidgets();
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
        return $tab;
    }

    public function setOptionalColumns(UxonObject $arrayOfColumns) : DataTableConfigurator
    {
        $this->columnsUxon = $arrayOfColumns;
        return $this;
    }

    public function hasOptionalColumns() : bool
    {
        return $this->columnsUxon !== null && $this->columnsUxon->isEmpty() === false;
    }

    public function addOptionalColumn(DataColumn $column) : DataTableConfigurator
    {
        $this->getOptionalColumnsTab()->addWidget($column);
        $column->setParent($this->getWidgetConfigured());
        return $this;
    }

    /**
     * 
     * @return \exface\Core\Widgets\DataTableConfigurator
     */
    protected function initColumns() : DataTableConfigurator
    {
        if (! $this->hasOptionalColumns()) {
            return $this;
        }
        $table = $this->getWidgetConfigured();
        // Do not create the columns for the table itself because that would reserver column
        // ids in the main column groug, eventually resulting in shifting ids when optional
        // columns are added. Instead create a detached column group and use that.
        // IDEA maybe we don't even need a column group? Couldn't we just create a detached
        // column with the columns-tab as parent?
        $colGrp = WidgetFactory::createFromUxonInParent($table, new UxonObject([
            'visibility' => WidgetVisibilityDataType::OPTIONAL
        ]), 'DataColumnGroup');
        foreach($this->columnsUxon as $columnUxon){
            $column = $colGrp->createColumnFromUxon($columnUxon);
            if(! $columnUxon->getProperty('visibility')){
                $column->setVisibility($this->columnsDefaultVisibility);
            }
            $colGrp->addColumn($column);
            $this->addOptionalColumn($column);
        }
        return $this;
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