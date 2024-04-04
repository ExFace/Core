<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\Input;

trait JqueryInputTrait
{
    /**
     * Returns <input type="xxx"> with the appropriate name, value, id, etc.
     * 
     * The parameter $propertyValues can be used to override default HTML properties
     * or add new ones: ['data-myprop' => 'myVal'] would result in data-myprop="myVal"
     * within the <input>.
     * 
     * @param string $type
     * @param string[] $propertyValues
     * 
     * @return string
     */
    protected function buildHtmlInput(string $type, array $propertyValues = []) : string
    {
        $defaults = [
            'name' => $this->getWidget()->getAttributeAlias(),
            'value' => $this->escapeString($this->getWidget()->getValueWithDefaults(), false, true),
            'id' => $this->getId()
        ];
        
        $propArray = array_merge($defaults, $propertyValues);
        $props = '';
        foreach ($propArray as $p => $v) {
            $props .= $p . '="' . $v . '" ';
        }
        
        return '<input type="' . $type . '" ' . $props . ' />';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement::buildJsCallFunction()
     */
    public function buildJsCallFunction(string $functionName = null, array $parameters = []) : string
    {
        switch (true) {
            case $functionName === Input::FUNCTION_FOCUS:
                return "setTimeout(function(){ $('#{$this->getId()}').focus(); }, 0);";
            case $functionName === Input::FUNCTION_EMPTY:
                return "setTimeout(function(){ {$this->buildJsEmpty()} }, 0);";
            case $functionName === Input::FUNCTION_REQUIRE:
                return "setTimeout(function(){ {$this->buildJsSetRequired(true)} }, 0);";
            case $functionName === Input::FUNCTION_UNREQUIRE:
                return "setTimeout(function(){ {$this->buildJsSetRequired(false)} }, 0);";
        }
        return parent::buildJsCallFunction($functionName, $parameters);
    }
    
    /**
     * javascript to get if an input is required or not, must not end with a semicolon!
     *
     * @return string
     */
    protected function buildJsRequiredGetter() : string
    {
        return "($('#{$this->getId()}').prop('required') != undefined)";
    }
    
    /**
     * 
     * @param bool $required
     * @return string
     */
    protected function buildJsSetRequired(bool $required) : string
    {
        if ($required === true) {
            return "$('#{$this->getId()}').prop('required', 'required');";
        } else {
            return "$('#{$this->getId()}').removeProp('required');";
        }
    }
    
    /**
     * 
     * @return string
     */
    protected function buildJsEmpty() : string
    {
        return <<<JS
        (function(){
			var val = {$this->buildJsValueGetter()};
            if (val !== undefined && val !== '' && val !== null) {
                {$this->buildJsValueSetter("''")}
            }
        })()
JS;
    }
}