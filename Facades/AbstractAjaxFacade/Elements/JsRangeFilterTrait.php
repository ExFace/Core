<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\InlineGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;

/**
 * Renders a RangeFilter as an InlineGroup with two default editors.
 * 
 * @method InlineGroup getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author aka
 *
 */
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
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        $facade = $this->getFacade();
        $js = '';
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $w) {
            $js .= $facade->getElement($w)->buildJsResetter() . ';';
        }
        return $js;
    }
    
    /**
     * 
     * @throws FacadeLogicError
     * @return string
     */
    public function buildJsValueGetter()
    {
        $valueGetters = [];
        $facade = $this->getFacade();
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $w) {
            if ($w instanceof iTakeInput) {
                $valueGetters[] = $facade->getElement($w)->buildJsValueGetter();
            }
        }
        
        if (count($valueGetters) !== 2) {
            throw new FacadeRuntimeError('Cannot get value of RangFilter: cannot find from/to-inputs!');
        }
        
        $fromGetterJs = $valueGetters[0];
        $toGetterJs = $valueGetters[1];
        $comparator = ComparatorDataType::BETWEEN;
        return <<<JS
(function(){
    var fromVal = $fromGetterJs;
    var toVal = $toGetterJs;
    if ((fromVal === undefined || fromVal === null || fromVal === '') && (toVal === undefined || toVal === null || toVal === '')) {
        return '';
    } else {
        return fromVal + '$comparator' + toVal;
    }
}())
JS;
    }
    
    /**
     * There is no value getter method for this class, because the logic of the value getter
     * (see above) cannot be easily packed into a single method to be called on the control.
     * 
     * @throws FacadeLogicError
     * 
     * @see JqueryFilterTrait::buildJsValueGetterMethod()
     */
    public function buildJsValueGetterMethod()
    {
        throw new FacadeLogicError('Cannot use JsRangeFilterTrait::buildJsValueGetterMethod() - use buildJsValueGetter() instead!');
    }
}