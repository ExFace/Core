<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InlineGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

trait JsRangeFilterTrait
{

    private $inlineGroup = null;
    
    /**
     *
     * @return InlineGroup
     */
    protected function getWidgetInlineGroup() : InlineGroup
    {
        if ($this->inlineGroup === null) {
            $widget = $this->getWidget();
            $wg = WidgetFactory::create($widget->getPage(), 'InlineGroup', $widget);
            $wg->setSeparator('-');
            
            $inputUxon = $widget->getInputWidget()->exportUxonObject();
            $inputUxon->setProperty('hide_caption', true);
            $filterFromUxon = new UxonObject([
                'widget_type' => 'Filter',
                'hide_caption' => true,
                'input_widget' => $inputUxon
            ]);
            $filterFromUxon->setProperty('comparator', $widget->getComparatorFrom());
            $filterTo = $filterFromUxon->copy();
            $filterTo->setProperty('comparator', $widget->getComparatorTo());
            
            if ($widget->hasValueFrom() === true) {
                $filterFromUxon->setProperty('value', $widget->getValueFrom());
            }
            if ($widget->hasValueTo() === true) {
                $filterTo->setProperty('value', $widget->getValueTo());
            }
            
            $groupWidgets = new UxonObject([
                $filterFromUxon,
                $filterTo
            ]);
            
            $wg->setWidgets($groupWidgets);
            $wg->setCaption($widget->getCaption());
            
            $this->inlineGroup = $wg;
        }
        return $this->inlineGroup;
    }
    
    /**
     *
     * @param string|null $valueJs
     */
    public function buildJsConditionGetter($valueJs = null)
    {
        $conditions = [];
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $filter) {
            $filterEl = $this->getFacade()->getElement($filter);
            if (method_exists($filterEl, 'buildJsConditionGetter') === true) {
                $conditions[] = $filterEl->buildJsConditionGetter($valueJs);
            }
        }
        return implode(',', $conditions);
    }

}