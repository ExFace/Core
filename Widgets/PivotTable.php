<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\Parts\Pivot\PivotLayout;

class PivotTable extends DataTable
{
    private $layout = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::init()
     */
    protected function init()
    {
        $this->setPaginate(false);
        $this->setShowRowNumbers(false);
        $this->setMultiSelect(false);
        $this->setColumnsAutoAddDefaultDisplayAttributes(false);
        $this->layout = new PivotLayout($this);
    }
    
    /**
     * 
     * @return PivotLayout
     */
    public function getPivotLayout() : PivotLayout
    {
        return $this->layout;
    }
    
    /**
     * Initial layout for the pivot table
     * 
     * @uxon-property pivot_layout
     * @uxon-type \exface\Core\Widgets\Parts\Pivot\PivotLayout
     * @uxon-template {"columns": [""], "rows": [""], "values": [{"attribute_alias": "", "aggregator": ""}]}
     * 
     * @param UxonObject $value
     * @return PivotTable
     */
    public function setPivotLayout(UxonObject $value) : PivotTable
    {
        $this->layout = new PivotLayout($this, $value);
        return $this;
    }
}