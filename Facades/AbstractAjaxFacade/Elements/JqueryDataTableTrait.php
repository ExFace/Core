<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\DataTable;

/**
 * This trait contains common methods for facades elements inheriting from the AbstractJqueryElement and representing
 * the DataTable widget.
 *
 * @method DataTable getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryDataTableTrait {
    
    private $editable = null;
    
    private $editors = [];

    /**
     * Builds an anonymous JS function returning the JSON representation of the condition group in the filters of the DataTable.
     *
     * This function is handy when building JS data or prefill objects.
     *
     * @return string
     */
    protected function buildJsDataFilters()
    {
        $widget = $this->getWidget();
        $detail_filters_js = '
				var filters = {operator: "AND", conditions: []}
			';
        foreach ($widget->getFilters() as $filter) {
            if ($filter->isDisplayOnly()) {
                continue;
            }
            $filter_element = $this->getFacade()->getElement($filter);
            $detail_filters_js .= '
				if (' . $filter_element->buildJsValueGetter() . '){
					filters.conditions.push(' . $filter_element->buildJsConditionGetter() . ');
				}';
        }
        return 'function(){' . $detail_filters_js . ' return filters;}()';
    }
    
    public function isEditable()
    {
        if ($this->editable === null) {
            $columns = $this->getWidget()->getColumns();
            foreach ($columns as $col) {
                if ($col->isEditable()) {
                    $this->editable = true;
                    break;
                }
            }
        }
        return $this->editable;
    }
    
    public function setEditable($value)
    {
        $this->editable = $value;
    }
    
    public function getEditors()
    {
        return $this->editors;
    }
    
    protected abstract function buildJsDataResetter() : string;
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement
     */
    public function buildJsResetter() : string
    {
        $configuratorElement = $this->getFacade()->getElement($this->getWidget()->getConfiguratorWidget());
        return $this->buildJsDataResetter() . ';' . $configuratorElement->buildJsResetter();
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsEnabler()
     */
    public function buildJsEnabler()
    {
        $js = '';
        $facade = $this->getFacade();
        foreach ($this->getWidget()->getFilters() as $filter) {
            $js .= $facade->getElement($filter)->buildJsEnabler() . ";\n";
        }
        foreach ($this->getWidget()->getButtons() as $btn) {
            $js .= $facade->getElement($btn)->buildJsEnabler() . ";\n";
        }
        return $js;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsDisabler()
     */
    public function buildJsDisabler()
    {
        $js = '';
        $facade = $this->getFacade();
        foreach ($this->getWidget()->getFilters() as $filter) {
            $js .= $facade->getElement($filter)->buildJsDisabler() . ";\n";
        }
        foreach ($this->getWidget()->getButtons() as $btn) {
            $js .= $facade->getElement($btn)->buildJsDisabler() . ";\n";
        }
        return $js;
    }
}