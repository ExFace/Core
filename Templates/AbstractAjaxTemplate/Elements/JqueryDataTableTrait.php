<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\DataTable;

/**
 * This trait contains common methods for templates elements inheriting from the AbstractJqueryElement and representing
 * the DataTable widget.
 *
 * @method DataTable getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryDataTableTrait {
    
    private $editable = false;
    
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
            $filter_element = $this->getTemplate()->getElement($filter);
            $detail_filters_js .= '
				if (' . $filter_element->buildJsValueGetter() . '){
					filters.conditions.push(' . $filter_element->buildJsConditionGetter() . ');
				}';
        }
        return 'function(){' . $detail_filters_js . ' return filters;}()';
    }
    
    public function isEditable()
    {
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
}
?>
