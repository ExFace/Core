<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DataConfigurator;

/**
 * 
 * @method DataConfigurator getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryDataConfiguratorTrait 
{
    protected function init()
    {
        $result = parent::init();
        
        $widget = $this->getWidget();
        $data_element = null;
        foreach ($widget->getFilters() as $filter) {
            // For each filter with auto-apply trigger a refresh once the value of the filter changes.
            if ($filter->getApplyOnChange()) {
                $data_element = is_null($data_element) ? $this->getTemplate()->getElement($widget->getWidgetConfigured()) : $data_element;
                $filter_element = $this->getTemplate()->getElement($filter);
                // Wrap the refresh in setTimeout() to make sure multiple filter can get their values before
                // one of the actually triggers the refresh. This also solved a strange bug, where the refresh
                // did not start with the first value change, but only with the second one an onwards.
                $filter_element->addOnChangeScript('setTimeout(function(){' . $data_element->buildJsRefresh() . '}, 50)');
            }
        }
        
        return $result;
    }
    
    /**
     * The data JS-object of a configurator widget contains filters, sorters, etc., that are
     * required to read the corresponding data.
     * 
     * NOTE: since data widgets are sometimes used within other widgets (i.e. InputComboTable)
     * without being really rendered, the data getter can be operate in two modes: rendered
     * and unrendered. While the former relies on the current value of rendered elements,
     * the latter will only include values defined in UXON. 
     * 
     * @param ActionInterface $action
     * @param boolean $unrendered
     * @return string
     */
    public function buildJsDataGetter(ActionInterface $action = null, $unrendered = false)
    {
        $widget = $this->getWidget();
        $filters = [];
        if (! $unrendered) {
            foreach ($widget->getFilters() as $filter) {
                $filters[] = $this->getTemplate()->getElement($filter)->buildJsConditionGetter();
            }
        } else {
            foreach ($widget->getFilters() as $filter) {
                if ($link = $filter->getValueWidgetLink()) {
                    $linked_element = $this->getTemplate()->getElement($link->getTargetWidget());
                    $filter_value = $linked_element->buildJsValueGetter($link->getTargetColumnId());
                } else {
                    $filter_value = '"' . $filter->getValue() . '"';
                }
                $filters[] = $this->getTemplate()->getElement($filter)->buildJsConditionGetter($filter_value);
            }
        }
        $filter_group = ! empty($filters) ? '{operator: "AND", conditions: [' . implode(', ', $filters) . ']}' : '';
        return "{oId: '" . $widget->getMetaObject()->getId() . "'" . ($filter_group !== '' ? ", filters: " . $filter_group : "") . "}";
    }
    
    public function buildJsRefreshOnEnter()
    {
        // Use keyup() instead of keypress() because the latter did not work with jEasyUI combos.
        return <<<JS
        setTimeout(function(){
            $('#{$this->getId()}').find('input').keyup(function (ev) {
                var keycode = (ev.keyCode ? ev.keyCode : ev.which);
                if (keycode == '13') {
                    {$this->getTemplate()->getElement($this->getWidget()->getWidgetConfigured())->buildJsRefresh()};
                }
            })
        }, 10)

JS;
    }
                
    /**
     * In a configurator, all filters must be validated before it's data can be used.
     * 
     * @see JqueryContainerTrait::getWidgetsToValidate()
     */
    protected function getWidgetsToValidate()
    {
        return $this->getWidget()->getFilters();
    }
}