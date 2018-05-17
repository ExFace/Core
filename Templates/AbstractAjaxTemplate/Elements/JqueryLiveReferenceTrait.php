<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iHaveValue;

/**
 * 
 * @method iHaveValue getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryLiveReferenceTrait {

    /**
     * Returns javascript code to transfer the value from the referenced element to
     * the one owning the reference.
     * 
     * The code ends with a semicolon.
     * 
     * @return string
     */
    protected function buildJsLiveReference()
    {
        $output = '';
        if ($linked_element = $this->getLinkedTemplateElement()) {
            $output = '
					' . $this->buildJsValueSetter($linked_element->buildJsValueGetter($this->getWidget()->getValueWidgetLink()->getTargetColumnId())) . ';';
        }
        return $output;
    }
    
    /**
     * Returns TRUE if this element has a live reference to fetch a value
     * from another element and FALSE otherwise.
     * 
     * @return boolean
     */
    protected function hasLiveReference()
    {
        return $this->getWidget()->getValueWidgetLink() ? true : false;
    }

    /**
     * Adds an on-change script to the referenced element to make sure, this element 
     * is always updated, once the referenced value changes.
     *
     * @return AbstractJqueryElement
     */
    protected function registerLiveReferenceAtLinkedElement()
    {
        if ($linked_element = $this->getLinkedTemplateElement()) {
            $linked_element->addOnChangeScript($this->buildJsLiveReference());
        }
        return $this;
    }
    
    /**
     * Returns the referenced template element or NULL if this element has no live reference.
     * 
     * @return AbstractJqueryElement||NULL
     */
    public function getLinkedTemplateElement()
    {
        $linked_element = null;
        if ($link = $this->getWidget()->getValueWidgetLink()) {
            $linked_element = $this->getTemplate()->getElement($link->getTargetWidget());
        }
        return $linked_element;
    }

}
?>
