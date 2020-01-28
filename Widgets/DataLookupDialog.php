<?php
namespace exface\Core\Widgets;

use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iUseInputWidget;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;
use exface\Core\CommonLogic\UxonObject;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class DataLookupDialog extends Dialog
{
    private $multi_select = null;
    
    private $lookupWidget = null;
    
    private $lookupWidgetUxon = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::getWidgets()
     */
    public function getWidgets(callable $filter = null)
    {
        if (parent::hasWidgets() === false) {
            $this->addWidget($this->getLookupWidget());
        }
        
        return parent::getWidgets($filter);
    }
    
    /**
     * Set to TRUE to allow selection of multiple entries in the lookup dialog.
     * 
     * If the lookup dialog is called from an input widget (e.g. `InputComboTable`) this setting
     * is inherited from that input. Otherwise it is `false` by default.
     * 
     * @uxon-property multi_select
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return DataLookupDialog
     */
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
        return $this->getLookupWidget();
    }
    
    public function getLookupWidget() : Data
    {
        if ($this->lookupWidget === null) {
            if ($this->lookupWidgetUxon === null) {
                $this->lookupWidget = $this->createLookupWidgetFromModel();
            } elseif(parent::hasWidgets() === false) {
                $this->lookupWidget = parent::getWidgetFirst();
            } else {
                $this->lookupWidget = WidgetFactory::createFromUxonInParent($this, $this->lookupWidgetUxon, 'DataTableResponsive');
            }
        }
        return $this->lookupWidget;
    }
    
    protected function createLookupWidgetFromModel() : Data
    {
        /* @var $data_table \exface\Core\Widgets\Data */
        $data_table = WidgetFactory::create($this->getPage(), 'DataTableResponsive', $this);
        $data_table->setMetaObject($this->getMetaObject());
        $data_table->setMultiSelect($this->getMultiSelect());
        
        $dataConfigurator = $data_table->getConfiguratorWidget();
        foreach($data_table->getColumns() as $col) {
            if ($col->isHidden() === false && $col->isBoundToAttribute() === true) {
                $colAttr = $col->getAttribute();
                switch (true) {
                    case $col->isBoundToLabelAttribute() === true:
                    case $colAttr->getRelationPath()->isEmpty() === false && $colAttr->isLabelForObject() === true:
                        $filterAttrAlias = $col->getAttribute()->getRelationPath()->toString();
                        break;
                    default:
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
        
        if ($data_table->getMetaObject()->hasLabelAttribute() === true) {
            $labelAlias = $data_table->getMetaObject()->getLabelAttributeAlias();
            if (! $data_table->getColumnByAttributeAlias($labelAlias) && ! $data_table->getColumnByDataColumnName($this->getWorkbench()->getConfig()->getOption("METAMODEL.OBJECT_LABEL_ALIAS"))) {
                $data_table->addColumn($data_table->createColumnFromAttribute($data_table->getMetaObject()->getLabelAttribute()));
            }
        }
        
        return $data_table;
    }
    
    /**
     * The widget to display selectable data (`DataTableResponsive` by default)
     * 
     * If not specified, a `DataTableResponsive` showing the default display column for the 
     * meta object will be generated automatically. The table will also have filters for
     * each column.
     * 
     * @uxon-property lookup_widget
     * @uxon-type \exface\Core\Widgets\DataTableResponsive
     * @uxon-template {"widget_type": ""}
     * 
     * @param UxonObject $uxon
     * @return DataLookupDialog
     */
    public function setLookupWidget(UxonObject $uxon) : DataLookupDialog
    {
        $this->lookupWidgetUxon = $uxon;
        $this->lookupWidget = null;
        return $this;
    }
}