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
    
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        foreach ($widget->getFilters() as $filter) {
            $filters .= ', ' . $this->getTemplate()->getElement($filter)->buildJsConditionGetter();
        }
        $filters = $filters ? '{operator: "AND", conditions: [' . trim($filters, ",") . ']}' : '';
        return "{oId: '" . $widget->getMetaObject()->getId() . "'" . ($filters ? ", filters: " . $filters : "") . "}";
    }
    
    public function buildJsRefreshOnEnter()
    {
        // Use keyup() instead of keypress() because the latter did not work with jEasyUI combos.
        return <<<JS

        $('#{$this->getId()}').find('input').keyup(function (ev) {
            var keycode = (ev.keyCode ? ev.keyCode : ev.which);
            if (keycode == '13') {
                {$this->getTemplate()->getElement($this->getWidget()->getWidgetConfigured())->buildJsRefresh()};
            }
        })

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