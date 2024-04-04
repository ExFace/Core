<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InlineGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;

/**
 * Renders a RangeFilter as an InlineGroup with two default editors.
 * 
 * @method \exface\Core\Widgets\RangeSpinnerFilter getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JsRangeSpinnerFilterTrait
{
    use JsRangeFilterTrait;
    
    protected abstract function buildCssWidthOfStepButton() : string;
    
    protected abstract function buildCssWidthOfRangeSeparator() : string;
    
    /**
     *
     * @return InlineGroup
     */
    protected function getWidgetInlineGroup() : InlineGroup
    {
        if ($this->inlineGroup === null) {
            $widget = $this->getWidget();
            $wg = WidgetFactory::create($widget->getPage(), 'InlineGroup', $widget);
            
            $inputUxon = $widget->getInputWidget()->exportUxonObject();
            $inputUxon->setProperty('hide_caption', true);
            $filterFromUxon = new UxonObject([
                'widget_type' => 'Filter',
                'hide_caption' => true,
                'input_widget' => $inputUxon
            ]);
            $filterFromUxon->setProperty('comparator', $widget->getComparatorFrom());
            $filterToUxon = $filterFromUxon->copy();
            $filterToUxon->setProperty('comparator', $widget->getComparatorTo());
            
            if ($widget->hasValueFrom() === true) {
                $filterFromUxon->setProperty('value', $widget->getValueFrom());
            }
            if ($widget->hasValueTo() === true) {
                $filterToUxon->setProperty('value', $widget->getValueTo());
            }
            
            $groupWidgets = new UxonObject([
                new UxonObject([
                    'widget_type' => 'Button',
                    'caption' => 'Previous',
                    'icon' => 'chevron-left',
                    'hide_caption' => true,
                    'width' => $this->buildCssWidthOfStepButton(),
                    'action' => [
                        'alias' => 'exface.Core.CallWidgetFunction',
                        'widget_id' => $this->getWidget()->getId(),
                        'function' => 'add(-' . $widget->getValueStep() . ')'
                    ]
                ]),
                $filterFromUxon,
                new UxonObject([
                    "widget_type" => "Text",
                    "text" => '-',
                    "align" => "center",
                    "width" => $this->buildCssWidthOfRangeSeparator(),
                    "multi_line" => false
                ]),
                $filterToUxon,
                new UxonObject([
                    'widget_type' => 'Button',
                    'caption' => 'Next',
                    'icon' => 'chevron-right',
                    'hide_caption' => true,
                    'width' => $this->buildCssWidthOfStepButton(),
                    'action' => [
                        'alias' => 'exface.Core.CallWidgetFunction',
                        'widget_id' => $this->getWidget()->getId(),
                        'function' => 'add(' . $widget->getValueStep() . ')'
                    ]
                ])
            ]);
            
            $wg->setWidgets($groupWidgets);
            $wg->setCaption($widget->getCaption());
            
            $this->inlineGroup = $wg;
            $this->getFacade()->getElement($this->inlineGroup)->addElementCssClass('exf-spinner-filter exf-spinner-range');
        }
        return $this->inlineGroup;
    }
}