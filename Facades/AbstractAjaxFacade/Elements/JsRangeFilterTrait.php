<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Widgets\InlineGroup;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\Facades\FacadeLogicError;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Widgets\RangeFilter;

/**
 * Renders a RangeFilter as an InlineGroup with two default editors.
 * 
 * @method \exface\Core\Widgets\RangeFilter getWidget()
 * @method AbstractAjaxFacade getFacade()
 * 
 * @author Andrej Kabachnik
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
                'required' => $widget->isRequired(),
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
                $filterFromUxon,
                $filterToUxon
            ]);
            
            $wg->setWidgets($groupWidgets);
            $wg->setCaption($widget->getCaption());
            
            $this->inlineGroup = $wg;
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
     * @param string|null $valueJs
     */
    public function buildJsConditionGetter($valueJs = null, MetaObjectInterface $baseObject = null)
    {
        $conditions = [];
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $filter) {
            $filterEl = $this->getFacade()->getElement($filter);
            if (method_exists($filterEl, 'buildJsConditionGetter') === true) {
                $conditions[] = $filterEl->buildJsConditionGetter($valueJs, $baseObject);
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
    public function buildJsValueGetter(string $column = null)
    {
        $valueGetters = [];
        $facade = $this->getFacade();
        
        if ($column === RangeFilter::VALUE_FROM) {
            return $facade->getElement($this->getWidgetInlineGroup()->getInputWidgets()[0])->buildJsValueGetter();
        }
        
        if ($column === RangeFilter::VALUE_TO) {
            return $facade->getElement($this->getWidgetInlineGroup()->getInputWidgets()[1])->buildJsValueGetter();
        }
        
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
     * Since the range filter is a group of two filters, we need to validate both of them.
     * @param mixed $valJs
     * @return string
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        $widget = $this->getWidget();
        $aValidatorScripts = [];
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $w) {
            $aValidatorScripts[] = $this->getFacade()->getElement($w)->buildJsValidator();
        }
        $validatorJS = implode('&&', $aValidatorScripts);
        $constraintsJs = '';
        if ($widget->isRequired() === true) {
            $constraintsJs = "if (val === undefined || val === null || val === '') { bConstraintsOK = false }";
        }
        
        $valJs = $valJs ?? $this->buildJsValueGetter();
        if ($constraintsJs !== '') {
            return <<<JS

                    (
                    (function(val){
                    	console.log('Ich bin hier');
                        var bConstraintsOK = true;
                        $constraintsJs;
                        return bConstraintsOK;
                    })($valJs) 
                    && {$validatorJS}
                    )
JS;
        } else {
            return $validatorJS;
        }
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
    
    /**
     *
     * @param string $functionName
     * @param array $parameters
     * @return string
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasFunction($functionName, false)) {
            return parent::buildJsCallFunction($functionName, $parameters);
        }
        
        $js = '';
        foreach ($this->getWidgetInlineGroup()->getWidgets() as $child) {
            if ($child->hasFunction($functionName)) {
                $js .= $this->getFacade()->getElement($child)->buildJsCallFunction($functionName, $parameters);
            }
        }
        
        return $js;
    }
}