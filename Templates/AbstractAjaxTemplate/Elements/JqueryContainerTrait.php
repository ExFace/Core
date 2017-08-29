<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\Container;

/**
 *
 * @method Container getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryContainerTrait {

    public function generateHtml()
    {
        return $this->buildHtmlForChildren();
    }

    public function generateJs()
    {
        return $this->buildJsForChildren();
    }

    public function buildHtmlForChildren()
    {
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getTemplate()->generateHtml($subw) . "\n";
        }
        ;
        return $output;
    }

    public function buildJsForChildren()
    {
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getTemplate()->generateJs($subw) . "\n";
        }
        ;
        return $output;
    }

    public function buildHtmlForWidgets()
    {
        foreach ($this->getWidget()->getWidgets() as $subw) {
            $output .= $this->getTemplate()->generateHtml($subw) . "\n";
        }
        ;
        return $output;
    }

    public function buildJsForWidgets()
    {
        foreach ($this->getWidget()->getWidgets() as $subw) {
            $output .= $this->getTemplate()->generateJs($subw) . "\n";
        }
        ;
        return $output;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        /* @var $widget \exface\Core\Widgets\Container */
        $widget = $this->getWidget();
        $data_getters = array();
        // Collect JS data objects from all inputs in the container
        foreach ($widget->getInputWidgets() as $child) {
            if (! $child->implementsInterface('iSupportStagedWriting')) {
                $data_getters[] = $this->getTemplate()->getElement($child)->buildJsDataGetter($action);
            } else {
                // TODO get data from non-input widgets, that support deferred CRUD operations staging their data in the GUI
            }
        }
        if (count($data_getters) > 0) {
            // Merge all the JS data objects, but remember to overwrite the head oId in the resulting object with the object id
            // of the container itself at the end! Otherwise the object id of the last widget in the container would win!
            return "$.extend(true, {},\n" . implode(",\n", $data_getters) . ",\n{oId: '" . $widget->getMetaObjectId() . "'}\n)";
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
        foreach ($widget->getInputWidgets() as $child) {
            $validator = $this->getTemplate()->getElement($child)->buildJsValidator();
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
        foreach ($widget->getInputWidgets() as $child) {
            $validator = $this->getTemplate()->getElement($child)->buildJsValidator();
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
}
?>