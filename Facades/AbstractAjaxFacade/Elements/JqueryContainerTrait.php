<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Container;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;

/**
 *
 * @method Container getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryContainerTrait {

    public function buildHtmlForChildren()
    {
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildHtml() . "\n";
        }
        
        return $output;
    }

    public function buildJsForChildren()
    {
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJs() . "\n";
        }
        ;
        return $output;
    }

    public function buildHtmlForWidgets()
    {
        foreach ($this->getWidget()->getWidgets() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildHtml() . "\n";
        }
        ;
        return $output;
    }

    public function buildJsForWidgets()
    {
        foreach ($this->getWidget()->getWidgets() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJs() . "\n";
        }
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        /* @var $widget \exface\Core\Widgets\Container */
        $widget = $this->getWidget();
        $data_getters = array();
        // Collect JS data objects from all inputs in the container
        foreach ($widget->getInputWidgets() as $child) {
            if (! $child->implementsInterface('iSupportStagedWriting')) {
                $data_getters[] = $this->getFacade()->getElement($child)->buildJsDataGetter($action);
            } else {
                // TODO get data from non-input widgets, that support deferred CRUD operations staging their data in the GUI
            }
        }
        if (count($data_getters) > 0) {
            // Merge all the JS data objects, but remember to overwrite the head oId in the resulting object with the object id
            // of the container itself at the end! Otherwise the object id of the last widget in the container would win!
            return "$.extend(true, {},\n" . implode(",\n", $data_getters) . ",\n{oId: '" . $widget->getMetaObject()->getId() . "'}\n)";
        } else {
            return '{}';
        }
    }
    
    /**
     * Returns an inline JS snippet which validates the input elements of the container.
     * Returns true if all elements are valid, returns false if at least one element is
     * invalid.
     *
     * @return string
     */
    public function buildJsValidator()
    {
        $widget = $this->getWidget();
        
        $output = '
				(function(){';
        foreach ($this->getWidgetsToValidate() as $child) {
            $validator = $this->getFacade()->getElement($child)->buildJsValidator();
            $output .= '
					if(!' . $validator . ') { return false; }';
        }
        $output .= '
					return true;
				})()';
        
        return $output;
    }
    
    /**
     * Returns a JavaScript snippet which handles the situation where not all input elements are
     * valid.
     * The invalid elements are collected and an error message is displayed.
     *
     * @return string
     */
    public function buildJsValidationError()
    {
        $widget = $this->getWidget();
        
        $output = '
				var invalidElements = [];';
        foreach ($this->getWidgetsToValidate() as $child) {
            $validator = $this->getFacade()->getElement($child)->buildJsValidator();
            if (! $alias = $child->getCaption()) {
                $alias = method_exists($child, 'getAttributeAlias') ? $child->getAttributeAlias() : $child->getMetaObject()->getAliasWithNamespace();
            }
            $output .= '
				if(!' . $validator . ') { invalidElements.push("' . $alias . '"); }';
        }
        $output .= '
				' . $this->buildJsShowMessageError('"' . $this->translate('MESSAGE.FILL_REQUIRED_ATTRIBUTES') . '" + invalidElements.join(", ")');
        
        return $output;
    }
    
    /**
     * Returns all children of the widget represented by this element, that need validation
     * 
     * @return \exface\Core\Interfaces\WidgetInterface[]
     */
    protected function getWidgetsToValidate()
    {
        return $this->getWidget()->getInputWidgets();
    }
    
    /**
     * Builds a JS snippet wrapped in an IIFE, that fills values of elements in the container with
     * data from the given JS data sheet. 
     * 
     * The input must be valid JS code representing or returning a JS data sheet.
     * 
     * For example, this code will extract data from a table and put it into a container:
     * $container->buildJsDataSetter($table->buildJsDataGetter())
     * 
     * @param string $jsData
     * @return string
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        $setters = '';
        foreach ($this->getWidget()->getWidgets() as $child) {
            if (! ($child instanceof iShowSingleAttribute) || ! $child->isBoundToAttribute()) {
                continue;
            }
            $setters .= <<<JS
            
                if (row['{$child->getAttributeAlias()}']) {
                    {$this->getFacade()->getElement($child)->buildJsValueSetter('row["' . $child->getAttributeAlias() . '"]')};
                }
JS;
        }
        return <<<JS

        function() {
            var data = {$jsData};
            var row = data.rows[0];
            if (! row || row.length === 0) {
                return;
            }
            {$setters}
        }()

JS;
    }
            
    public function buildJsDestroy() : string
    {
        $output = '';
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJsDestroy() . "\n";
        }
        return $output;
    }
}
?>