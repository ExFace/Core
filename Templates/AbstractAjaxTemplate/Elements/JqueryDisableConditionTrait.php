<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Factories\WidgetLinkFactory;

/**
 * 
 * @method iHaveValue getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryDisableConditionTrait {

    /**
     * Returns a JavaScript-snippet, which is registered in the onChange-Script of the
     * element linked by the disable condition.
     * Based on the condition and the value
     * of the linked widget, it enables and disables the current widget.
     *
     * @return string
     */
    public function buildJsDisableCondition()
    {
        $output = '';
        $widget = $this->getWidget();
        if (($condition = $widget->getDisableCondition()) && $condition->getProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getTemplate()->getElement($link->getTargetWidget());
            if ($linked_element) {
                switch ($condition->getProperty('comparator')) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $enable_widget_script = $widget->isDisabled() ? '' : $this->buildJsEnabler() . ';';
                        
                        $output = <<<JS

						if ({$linked_element->buildJsValueGetter($link->getTargetColumnId())} {$condition->getProperty('comparator')} "{$condition->getProperty('value')}") {
							{$this->buildJsDisabler()};
						} else {
							{$enable_widget_script}
						}
JS;
                        break;
                    case EXF_COMPARATOR_IN: // [
                    case EXF_COMPARATOR_NOT_IN: // ![
                    case EXF_COMPARATOR_IS: // =
                    default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
                }
            }
        }
        return $output;
    }

    /**
     * Returns a JavaScript-snippet, which initializes the disabled-state of elements
     * with a disabled condition.
     *
     * @return string
     */
    public function buildJsDisableConditionInitializer()
    {
        $output = '';
        $widget = $this->getWidget();
        /* @var $condition \exface\Core\CommonLogic\UxonObject */
        if (($condition = $widget->getDisableCondition()) && $condition->hasProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getTemplate()->getElement($link->getTargetWidget());
            if ($linked_element) {
                switch ($condition->getProperty('comparator')) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $output .= <<<JS

						// Man muesste eigentlich schauen ob ein bestimmter Wert vorhanden ist: buildJsValueGetter(link->getTargetColumnId()).
						// Da nach einem Prefill dann aber normalerweise ein leerer Wert zurueckkommt, wird beim initialisieren
						// momentan einfach geschaut ob irgendein Wert vorhanden ist.
						if ({$linked_element->buildJsValueGetter()} {$condition->getProperty('comparator')} "{$condition->getProperty('value')}") {
							{$this->buildJsDisabler()};
						}
JS;
                        break;
                    case EXF_COMPARATOR_IN: // [
                    case EXF_COMPARATOR_NOT_IN: // ![
                    case EXF_COMPARATOR_IS: // =
                    default:
                    // TODO fuer diese Comparatoren muss noch der JavaScript generiert werden
                }
            }
        }
        return $output;
    }

    /**
     * Registers an onChange-Skript on the element linked by the disable condition.
     *
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\JqueryLiveReferenceTrait
     */
    protected function registerDisableConditionAtLinkedElement()
    {
        if ($linked_element = $this->getDisableConditionTemplateElement()) {
            $linked_element->addOnChangeScript($this->buildJsDisableCondition());
        }
        return $this;
    }

    /**
     * Returns the widget which is linked by the disable condition.
     *
     * @return
     *
     */
    public function getDisableConditionTemplateElement()
    {
        $linked_element = null;
        $widget = $this->getWidget();
        if (($condition = $widget->getDisableCondition()) && $condition->hasProperty('widget_link')) {
            $link = WidgetLinkFactory::createFromWidget($widget, $condition->getProperty('widget_link'));
            $linked_element = $this->getTemplate()->getElement($link->getTargetWidget());
        }
        return $linked_element;
    }
}
?>
