<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InlineGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\WidgetInterface;

/**
 * Renders a RangeFilter as an InlineGroup with two default editors.
 * 
 * @method \exface\Core\Widgets\SpinnerFilter getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JsSpinnerFilterTrait
{
    private $inlineGroup = null;
    
    protected abstract function buildCssWidthOfStepButton() : string;
    
    /**
     *
     * @return InlineGroup
     */
    protected function getWidgetInlineGroup() : InlineGroup
    {
        if ($this->inlineGroup === null) {
            $widget = $this->getWidget();
            $wg = WidgetFactory::create($widget->getPage(), 'InlineGroup', $widget);
            
            $inputWidget = $widget->getInputWidget();
            
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
                $inputWidget,
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
            $this->getFacade()->getElement($this->inlineGroup)->addElementCssClass('exf-spinner-filter');
        }
        return $this->inlineGroup;
    }
    
    public function addOnChangeScript($string)
    {
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $w) {
            $this->getFacade()->getElement($w)->addOnChangeScript($string);
        }
        return $this;
    }
    
    /**
     * 
     * @return string
     */
    public function buildJsValueGetter(string $column = null)
    {
        return $this->getFacade()->getElement($this->getWidgetInlineGroup()->getInputWidgets()[0])->buildJsValueGetter($column);
    }
}