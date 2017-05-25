<?php
namespace exface\Core\Widgets;

class PivotTable extends DataTable
{

    protected function init()
    {
        $this->setPaginate(false);
        $this->setShowRowNumbers(false);
        $this->setMultiSelect(false);
    }
}
?>