<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Factories\WidgetLinkFactory;

trait JqueryLiveReferenceTrait {

    protected function buildJsLiveReference()
    {
        $output = '';
        if ($link = $this->getWidget()->getValueWidgetLink()) {
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
            if ($linked_element) {
                $output = '
						' . $this->buildJsValueSetter($linked_element->buildJsValueGetter($link->getColumnId())) . ';';
            }
        }
        return $output;
    }

    /**
     * Makes sure, this element is always updated, once the value of a live reference changes - of course, only if there is a live reference!
     *
     * @return euiInput
     */
    protected function registerLiveReferenceAtLinkedElement()
    {
        if ($linked_element = $this->getLinkedTemplateElement()) {
            $linked_element->addOnChangeScript($this->buildJsLiveReference());
        }
        return $this;
    }

    public function getLinkedTemplateElement()
    {
        $linked_element = null;
        if ($link = $this->getWidget()->getValueWidgetLink()) {
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
        }
        return $linked_element;
    }

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
        if (($condition = $this->getWidget()->getDisableCondition()) && $condition->widget_link) {
            $link = WidgetLinkFactory::createFromAnything($this->getWorkbench(), $condition->widget_link);
            $link->setWidgetIdSpace($this->getWidget()->getIdSpace());
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
            if ($linked_element) {
                switch ($condition->comparator) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $enable_widget_script = $this->getWidget()->isDisabled() ? '' : $this->buildJsEnabler() . ';';
                        
                        $output = <<<JS

						if ({$linked_element->buildJsValueGetter($link->getColumnId())} {$condition->comparator} "{$condition->value}") {
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
        if (($condition = $this->getWidget()->getDisableCondition()) && $condition->widget_link) {
            $link = WidgetLinkFactory::createFromAnything($this->getWorkbench(), $condition->widget_link);
            $link->setWidgetIdSpace($this->getWidget()->getIdSpace());
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
            if ($linked_element) {
                switch ($condition->comparator) {
                    case EXF_COMPARATOR_IS_NOT: // !=
                    case EXF_COMPARATOR_EQUALS: // ==
                    case EXF_COMPARATOR_EQUALS_NOT: // !==
                    case EXF_COMPARATOR_LESS_THAN: // <
                    case EXF_COMPARATOR_LESS_THAN_OR_EQUALS: // <=
                    case EXF_COMPARATOR_GREATER_THAN: // >
                    case EXF_COMPARATOR_GREATER_THAN_OR_EQUALS: // >=
                        $output .= <<<JS

						// Man muesste eigentlich schauen ob ein bestimmter Wert vorhanden ist: build_js_value_getter(link->getColumnId()).
						// Da nach einem Prefill dann aber normalerweise ein leerer Wert zurueckkommt, wird beim initialisieren
						// momentan einfach geschaut ob irgendein Wert vorhanden ist.
						if ({$linked_element->buildJsValueGetter()} {$condition->comparator} "{$condition->value}") {
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
        if (($condition = $this->getWidget()->getDisableCondition()) && $condition->widget_link) {
            $link = WidgetLinkFactory::createFromAnything($this->getWorkbench(), $condition->widget_link);
            $link->setWidgetIdSpace($this->getWidget()->getIdSpace());
            $linked_element = $this->getTemplate()->getElementByWidgetId($link->getWidgetId(), $this->getPageId());
        }
        return $linked_element;
    }
}
?>
