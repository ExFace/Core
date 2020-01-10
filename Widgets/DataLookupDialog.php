<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class DataLookupDialog extends Dialog
{
    private $multi_select = null;
    
    public function getWidgets(callable $filter = null)
    {
        if (parent::hasWidgets() === false) {
            $data_table = WidgetFactory::create($this->getPage(), 'DataTable', $this);
            $data_table->setMetaObject($this->getMetaObject());
            $srcWidget = $this->getParent();
            if ($this->getMultiSelect() === null && $srcWidget instanceof iUseInputWidget) {
                $inputWidget = $srcWidget->getInputWidget();
                if ($inputWidget && ($inputWidget instanceof iSupportMultiSelect)){
                    $data_table->setMultiSelect($inputWidget->getMultiSelect());
                }
            }
            $this->addWidget($data_table);
            
            if ($data_table->getMetaObject()->hasLabelAttribute() === true) {
                $labelAlias = $data_table->getMetaObject()->getLabelAttributeAlias();
                if (! $data_table->getColumnByAttributeAlias($labelAlias) && ! $data_table->getColumnByDataColumnName($this->getWorkbench()->getConfig()->getOption("METAMODEL.OBJECT_LABEL_ALIAS"))) {
                    $data_table->addColumn($data_table->createColumnFromAttribute($data_table->getMetaObject()->getLabelAttribute()));
                }
            }
        }
        
        return parent::getWidgets($filter);
    }
    
    public function setMultiSelect(bool $trueOrFalse) : DataLookupDialog
    {
        $this->multi_select = $trueOrFalse;
        return $this;
    }
    
    protected function getMultiSelect() : ?bool
    {
        return $this->multi_select;
    }
    
    public function getDataWidget() : Data
    {
        return $this->getWidgetFirst();
    }
}