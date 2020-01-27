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
            /* @var $data_table \exface\Core\Widgets\Data */
            $data_table = WidgetFactory::create($this->getPage(), 'DataTableResponsive', $this);
            $data_table->setMetaObject($this->getMetaObject());
            $data_table->setMultiSelect($this->getMultiSelect());
            
            $dataConfigurator = $data_table->getConfiguratorWidget();
            foreach($data_table->getColumns() as $col) {
                if ($col->isHidden() === false && $col->isBoundToAttribute() === true) {
                    if ($col->isBoundToLabelAttribute() === true || ($col->getAttribute()->getRelationPath()->isEmpty() === false && $col->getAttribute()->isLabelForObject() === true)) {
                        $filterAttrAlias = $col->getAttribute()->getRelationPath()->toString();
                    } else {
                        $filterAttrAlias = $col->getAttributeAlias();
                    }
                    if ($filterAttrAlias !== '' || $filterAttrAlias !== null) {
                        $filterWidget = $dataConfigurator->createFilterWidget($filterAttrAlias);
                        if ($filterWidget->getInputWidget() instanceof iSupportMultiSelect) {
                            $filterWidget->getInputWidget()->setMultiSelect(true);
                        }
                        $dataConfigurator->addFilter($filterWidget);
                    }
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
    
    public function getMultiSelect() : ?bool
    {
       $srcWidget = $this->getParent();
        if ($srcWidget instanceof iUseInputWidget) {
            $inputWidget = $srcWidget->getInputWidget();
            if ($inputWidget && ($inputWidget instanceof iSupportMultiSelect)){
                return $inputWidget->getMultiSelect();
            }
        }
        return null;
    }
    
    public function getDataWidget() : Data
    {
        return $this->getWidgetFirst();
    }
}