<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Filter;

/**
 *
 * @method Filter getWidget()
 *        
 * @author aka
 *        
 */
trait JqueryFilterTrait {

    public function buildJsConditionGetter()
    {
        $widget = $this->getWidget();
        return '{expression: "' . $widget->getAttributeAlias() . '", comparator: "' . $widget->getComparator() . '", value: ' . $this->buildJsValueGetter() . ', object_alias: "' . $widget->getMetaObject()->getAliasWithNamespace() . '"}';
    }

    public function generateHtml()
    {
        return $this->getInputElement()->generateHtml();
    }

    public function generateJs()
    {
        return $this->getInputElement()->generateJs();
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
        return $this->getTemplate()->getElement($this->getWidget()->getInputWidget());
    }

    /**
     * Magic method to forward all calls to methods, not explicitly defined in the filter to ist value widget.
     * Thus, the filter is a simple proxy from the point of view of the template. However, it can be easily
     * enhanced with additional methods, that will override the ones of the value widget.
     * TODO this did not really work so far. Don't know why. As a work around, added some explicit proxy methods
     * -> __call wird aufgerufen, wenn eine un!zugreifbare Methode in einem Objekt aufgerufen wird
     * (werden die ueberschriebenen Proxymethoden entfernt, existieren sie ja aber auch noch euiInput)
     *
     * @param string $name            
     * @param array $arguments            
     */
    public function __call($name, $arguments)
    {
        return call_user_method_array($name, $this->getInputElement(), $arguments);
    }
}
?>
