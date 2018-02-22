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
            if ($filter->getApplyOnChange()) {
                $data_element = is_null($data_element) ? $this->getTemplate()->getElement($widget->getWidgetConfigured()) : $data_element;
                $filter_element = $this->getTemplate()->getElement($filter);
                $filter_element->addOnChangeScript($data_element->buildJsRefresh());
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
}