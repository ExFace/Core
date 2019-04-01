<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

/**
 * The ContextBar widget is rendered by the facade itself and is update via response extras.
 *
 * This implementation makes it possible to fetch context bar data by calling
 * the ShowWidget action via AJAX. This is needed for pages without ajax-based
 * widgets: since these pages do not make ajax requests, the context bar would
 * not get updated, so the facade will fetch the data explicitly.
 *
 * @author Andrej Kabachnik
 *
 */
trait JqueryContextBarAjaxTrait {

    public function buildJsonContextBarUpdate()
    {
        $widget = $this->getWidget();
        $extra = [];
        try {
            foreach ($widget->getButtons() as $btn){
                $btn_element = $this->getFacade()->getElement($btn);
                $context = $widget->getContextForButton($btn);
                $extra[$btn_element->getId()] = [
                    'visibility' => $context->getVisibility(),
                    'icon' => $btn_element->buildCssIconClass($btn->getIcon()),
                    'color' => $context->getColor(),
                    'hint' => $btn->getHint(),
                    'indicator' => ! is_null($context->getIndicator()) ? $widget->getContextForButton($btn)->getIndicator() : '',
                    'bar_widget_id' => $btn->getId()
                ];
            }
        } catch (\Throwable $e){
            $this->getWorkbench()->getLogger()->logException($e);
        }
        return $extra;
    }    
}