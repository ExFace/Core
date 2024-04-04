<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Value;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\DataTypes\StringDataType;

/**
 * This trait makes it easy to implement live references in jQuery based widgets.
 * 
 * Just call $this->registerLiveReferenceAtLinkedElement() in the init() method of
 * the element, that represents the widget, where the live reference is defined. This
 * will add an onChange-script to the referenced element, which sets the value
 * of our element via it's buildJsValueSetter(). 
 * 
 * Note, that the target element's implementation MUST support onChange-scripts.
 * If it does not, the live reference will not work! The implementation of the
 * onChange listener is not part of this trait!
 * 
 * Also note, that registerLiveReferenceAtLinkedElement() MUST be called before
 * that element is is actually rendered. This is not a problem for most facades, 
 * though, because the init all elements when generating <head> tags, thus
 * triggering the reference registration before even starting the HTML rendering.
 * 
 * @method \exface\Core\Interfaces\Widgets\iHaveValue getWidget()
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
        if (($this->getWidget() instanceof Value) && $this->getWidget()->isInTable() === true) {
            return '';
        }
        
        $output = '';
        if ($linked_element = $this->getLinkedFacadeElement()) {
            $link = $this->getWidget()->getCalculationWidgetLink();
            $col = $link->getTargetColumnId();
            if (! StringDataType::startsWith($col, '~')) {
                $col = DataColumn::sanitizeColumnName($col);
            }
            if ($link->isOnlyIfNotEmpty()) {
                $output = <<<JS

(function() {
    var mVal = {$linked_element->buildJsValueGetter($col, $link->getTargetRowNumber())};
    if (mVal !== undefined && mVal !== '' && mVal != null) {
        {$this->buildJsValueSetter('mVal')};
    }
})();

JS;
                
            } else {
                $output = '
					   ' . $this->buildJsValueSetter($linked_element->buildJsValueGetter($col, $link->getTargetRowNumber())) . ';';
            }
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
        return $this->getWidget()->getCalculationWidgetLink() ? true : false;
    }

    /**
     * Adds an on-change script to the referenced element to make sure, this element 
     * is always updated, once the referenced value changes.
     *
     * @return AbstractJqueryElement
     */
    protected function registerLiveReferenceAtLinkedElement()
    {
        if ($linked_element = $this->getLinkedFacadeElement()) {
            $getAndSetValueJs = $this->buildJsLiveReference();
            // If the link targets a specific row, activate it with every refresh,
            // otherwise it targets the current value, so only activate it if the value changes
            if (null !== $this->getWidget()->getCalculationWidgetLink()->getTargetRowNumber()) {
                $linked_element->addOnRefreshScript($getAndSetValueJs);
            } else {
                $linked_element->addOnChangeScript($getAndSetValueJs);
            }
        }
        return $this;
    }
    
    /**
     * Returns the referenced facade element or NULL if this element has no live reference.
     * 
     * @return AbstractJqueryElement||NULL
     */
    public function getLinkedFacadeElement()
    {
        $linked_element = null;
        if ($link = $this->getWidget()->getCalculationWidgetLink()) {
            $linked_element = $this->getFacade()->getElement($link->getTargetWidget());
        }
        return $linked_element;
    }

}
?>
