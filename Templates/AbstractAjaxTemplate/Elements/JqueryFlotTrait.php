<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

trait JqueryFlotTrait {

    protected function buildJsLiveReference()
    {
        $output = '';
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
            $output .= $this->buildJsFunctionPrefix() . 'plot(' . $linked_element->buildJsDataGetter() . ".rows);";
        }
        return $output;
    }

    /**
     * Makes sure, the Chart is always updated, once the linked data widget loads new data - of course, only if there is a data link defined!
     */
    protected function registerLiveReferenceAtLinkedElement()
    {
        if ($link = $this->getWidget()->getDataWidgetLink()) {
            /* @var $linked_element \exface\Templates\jEasyUI\Widgets\euiData */
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
            if ($linked_element) {
                $linked_element->addOnLoadSuccess($this->buildJsLiveReference());
            }
        }
        return $this;
    }
}
?>
