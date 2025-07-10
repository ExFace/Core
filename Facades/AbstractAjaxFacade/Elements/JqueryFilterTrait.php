<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Model\ConditionGroupInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 *
 * @method \exface\Core\Widgets\Filter getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryFilterTrait {

    public function buildJsConditionGetter($valueJs = null, MetaObjectInterface $baseObject = null)
    {
        $widget = $this->getWidget();
        if ($widget->hasCustomConditionGroup() === true) {
            return '';
        }
        if ($widget->isDisplayOnly() === true) {
            return '';
        }
        $value = is_null($valueJs) ? $this->buildJsValueGetter() : $valueJs;
        if ($widget->getAttributeAlias() === '' || $widget->getAttributeAlias() === null) {
            throw new WidgetConfigurationError($widget, 'Invalid filter configuration for filter "' . $widget->getCaption() . '": missing expression (e.g. attribute_alias)!');
        }

        if ($baseObject === null || ! $baseObject->isExactly($widget->getMetaObject())) {
            $metaObjectAliasJs = "\"object_alias\" : \"{$widget->getMetaObject()->getAliasWithNamespace()}\",";
        } else {
            $metaObjectAliasJs = '';
        }
        return <<<JSON
{
  "expression" : "{$widget->getAttributeAlias()}",
  "comparator" : {$this->buildJsComparatorGetter()},
  "value" : $value,
  "apply_to_aggregates" : {$this->escapeBool($widget->appliesToAggregatedValues())},
  {$metaObjectAliasJs}
}
JSON;
    }
    
    public function buildJsCustomConditionGroup($valueJs = null) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasCustomConditionGroup() === false) {
            return '';
        }

        $condGrp = $widget->getCustomConditionGroup();
        $replacements = $this->getCustomConditionGroupReplacements($condGrp);

        $jsonWithValuePlaceholder = $condGrp->exportUxonObject()->toJson(false);
        return str_replace(array_keys($replacements), array_values($replacements), $jsonWithValuePlaceholder);
    }

    protected function getCustomConditionGroupReplacements(ConditionGroupInterface $condGrp) : array
    {
        $widget = $this->getWidget();
        $replacements = [];
        $condGrpStr = $condGrp->exportUxonObject()->toJson(false);
        $phs = StringDataType::findPlaceholders($condGrpStr);
        foreach ($phs as $ph) {
            switch ($ph) {
                case 'value':
                case '~value':
                    $replacements['"[#' . $ph . '#]"'] = $valueJs ?? $this->buildJsValueGetter();
                    break;
                default:
                    throw new WidgetConfigurationError($this->getWidget(), 'Invalid placeholder "[#' . $ph . '#]" in custom condition_group of filter "' . $this->getWidget()->getCaption() . '"!');
            }
        }

        foreach ($condGrp->getConditionsRecursive() as $cond) {
            $val = $cond->getValue();
            if (Expression::detectReference($val)) {
                $valLink = WidgetLinkFactory::createFromWidget($widget, $val);
                $valueGetterJs = $this->getFacade()->getElement($valLink->getTargetWidget())->buildJsValueGetter();
                $replacements['"' . $val . '"' ] = $valueGetterJs;
            }
        }
        return $replacements;
    }
    
    public function buildJsComparatorGetter()
    {
        return '"' . $this->getWidget()->getComparator() . '"';
    }

    public function buildJsValueGetter()
    {
        return $this->getInputElement()->buildJsValueGetter();
    }

    public function buildJsValueGetterMethod()
    {
        return $this->getInputElement()->buildJsValueGetterMethod();
    }

    public function buildJsValueSetter($value)
    {
        return $this->getInputElement()->buildJsValueSetter($value);
    }

    public function buildJsValueSetterMethod($value)
    {
        return $this->getInputElement()->buildJsValueSetterMethod($value);
    }

    public function buildJsInitOptions()
    {
        return $this->getInputElement()->buildJsInitOptions();
    }

    public function getInputElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getInputWidget());
    }
    
    public function addOnChangeScript($string)
    {
        $this->getInputElement()->addOnChangeScript($string);
        return $this;
    }

    /**
     * Magic method to forward all calls to methods, not explicitly defined in the filter to ist value widget.
     * Thus, the filter is a simple proxy from the point of view of the facade. However, it can be easily
     * enhanced with additional methods, that will override the ones of the value widget.
     * TODO this did not really work so far. Don't know why. As a work around, added some explicit proxy methods
     * -> __call wird aufgerufen, wenn eine un!zugreifbare Methode in einem Objekt aufgerufen wird
     * (werden die ueberschriebenen Proxymethoden entfernt, existieren sie ja aber auch noch EuiInput)
     *
     * @param string $name            
     * @param array $arguments            
     */
    public function __call($name, $arguments)
    {
        return call_user_method_array($name, $this->getInputElement(), $arguments);
    }
    
    /**
     * A filter is valid as long as it is not empty while being required - all other validations
     * like checking data type constraints do not apply as a user may search for parts of a value.
     * 
     * It is also important to validate hidden filters too because their validity is checked
     * before making lazy data requests. This is another difference compared to regular inputs
     * as used in forms, etc.
     * 
     * IDEA On the other hand, checking data type constraints might be a good idea when using
     * ceratin comparators like EQUALS or EQUALS_NOT - i.e. when partial values are not accepted.
     * 
     * @see AbstractJqueryElement::buildJsValidator()
     */
    public function buildJsValidator(?string $valJs = null) : string
    {
        $widget = $this->getWidget();
        $constraintsJs = '';
        $validateInputWidgetJs = "&& {$this->getInputElement()->buildJsValidator()}";

        // If the filters itself is required, we need to double-check, it is not empty.
        if ($widget->isRequired() === true) {
            // However, the logic depends on whether the filter has a custom condition group or not.
            // If it has, the filter is valid if at least one of the condition values is not empty.
            if ($widget->hasCustomConditionGroup()) {
                $valueGetters = $this->getCustomConditionGroupReplacements($widget->getCustomConditionGroup());
                $valueGettersJs = implode(',', array_values($valueGetters));
                $constraintsJs = <<<JS
                
                            var aValues = [{$valueGettersJs}];
                            var aValuesNotEmpty = false;
                            aValues.forEach(function(val) {
                                if (val !== undefined && val !== null && val !== '') {
                                    aValuesNotEmpty = true;
                                }
                            });
                            bConstraintsOK = aValuesNotEmpty;
JS;
            } else {
                $constraintsJs = "if (val === undefined || val === null || val === '') { bConstraintsOK = false }";
            }
        }

        // Do not validate the original input widget if the filter has a custom condition group and it
        // does not use the input widget.
        if ($widget->hasCustomConditionGroup() && ($widget->isHidden() || ! $widget->isBoundToAttribute())) {
            $validateInputWidgetJs = '';
        }
        
        $valJs = $valJs ?? $this->buildJsValueGetter();
        if ($constraintsJs !== '') {
            return <<<JS

                    (
                        (function(val){
                            var bConstraintsOK = true;
                            $constraintsJs;
                            return bConstraintsOK;
                        })($valJs) 
                        {$validateInputWidgetJs}
                    )
JS;
        } else {
            return $this->getInputElement()->buildJsValidator();
        }
    }
    
    /**
     *
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsValidationError()
     */
    public function buildJsValidationError()
    {
        return $this->getInputElement()->buildJsValidationError();
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsResetter()
     */
    public function buildJsResetter() : string
    {
        return $this->getInputElement()->buildJsResetter();
    }
    
    /**
     *
     * @param string $functionName
     * @param array $parameters
     * @return string
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = [], ?string $jsRequestData = null) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasFunction($functionName, false)) {
            return parent::buildJsCallFunction($functionName, $parameters, $jsRequestData);
        }
        
        return $this->getFacade()->getElement($widget->getInputWidget())->buildJsCallFunction($functionName, $parameters, $jsRequestData);
    }
}