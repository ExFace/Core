<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 *
 * @method \exface\Core\Widgets\Container getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryContainerTrait {

    public function buildHtmlForChildren()
    {
        $output = '';
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildHtml() . "\n";
        }        
        return $output;
    }

    public function buildJsForChildren()
    {
        $output = '';
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJs() . "\n";
        }
        return $output;
    }

    public function buildHtmlForWidgets()
    {
        $output = '';
        foreach ($this->getWidget()->getWidgets() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildHtml() . "\n";
        }
        return $output;
    }

    public function buildJsForWidgets()
    {
        $output = '';
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
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter($dataColumnName = null)
    {
        if ($dataColumnName === null) {
            if ($this->getMetaObject()->hasUidAttribute()) {
                $dataColumnName = DataColumn::sanitizeColumnName($this->getMetaObject()->getUidAttributeAlias());
            } else {
                return parent::buildJsValueGetter($dataColumnName);
            }
        }
        return "({$this->buildJsDataGetter()}.rows[0] || {})['{$dataColumnName}']";
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
				' . $this->buildJsShowMessageError('"' . $this->translate('MESSAGE.FILL_REQUIRED_ATTRIBUTES') . ' " + invalidElements.join(", ")');
        
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
         
    /**
     * Destroying a container means destroying all children.
     * 
     * @see AbstractJqueryElement::buildJsDestroy()
     */
    public function buildJsDestroy() : string
    {
        $output = '';
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJsDestroy() . "\n";
        }
        return $output;
    }
    
    /**
     * Resetting a container means resetting all children.
     * 
     * @see AbstractJqueryElement::buildJsDestroy()
     */
    public function buildJsResetter() : string
    {
        $output = '';
        foreach ($this->getWidget()->getChildren() as $subw) {
            $output .= $this->getFacade()->getElement($subw)->buildJsResetter() . ";\n";
        }
        return $output;
    }
    
    /**
     * Registers a jQuery custom event handler that refreshes the container contents if effected by an action.
     *
     * Returns JS code to register a listener on `document` for the custom jQuery event
     * `actionperformed`. The listener will see if the widget configured is affected
     * by the event (e.g. by the action effects) and triggers a refresh on the widget.
     *
     * @param string $scriptJs
     * @return string
     */
    protected function buildJsRegisterOnActionPerformed(string $scriptJs) : string
    {
        $effectedAliases = [$this->getMetaObject()->getAliasWithNamespace()];
        foreach ($this->getWidget()->getWidgetsRecursive() as $child) {
            if (! (($child instanceof iShowSingleAttribute) && $child->isBoundToAttribute())) {
                continue;
            }
            $attr = $child->getAttribute();
            if ($attr->getRelationPath()->isEmpty()) {
                continue;
            }
            foreach ($attr->getRelationPath()->getRelations() as $rel) {
                $effectedAliases[] = $rel->getLeftObject()->getAliasWithNamespace();
                $effectedAliases[] = $rel->getRightObject()->getAliasWithNamespace();
            }
        }
        $effectedAliasesJs = json_encode(array_values(array_unique($effectedAliases)));
        $actionperformed = AbstractJqueryElement::EVENT_NAME_ACTIONPERFORMED;
        return <<<JS
        
$( document ).off( "{$actionperformed}.{$this->getId()}" );
$( document ).on( "{$actionperformed}.{$this->getId()}", function( oEvent, oParams ) {
    var oEffect = {};
    var aUsedObjectAliases = {$effectedAliasesJs};
    var sWidgetId = "{$this->getId()}";
    var fnRefresh = function() {
        {$scriptJs}
    };
    
    if (oParams.refresh_not_widgets.indexOf(sWidgetId) !== -1) {
        return;
    }
    
    if (oParams.refresh_widgets.indexOf(sWidgetId) !== -1) {
        fnRefresh();
        return;
    }
    
    for (var i = 0; i < oParams.effects.length; i++) {
        oEffect = oParams.effects[i];
        if (aUsedObjectAliases.indexOf(oEffect.effected_object) !== -1) {
            // refresh immediately if directly affected or delayed if it is an indirect effect
            if (oEffect.effected_object === '{$this->getWidget()->getMetaObject()->getAliasWithNamespace()}') {
                fnRefresh();
            } else {
                setTimeout(fnRefresh, 100);
            }
            return;
        }
    }
});

JS;
    }
}