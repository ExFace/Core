<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

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
            'value' => $this->getValueWithDefaults(),
            'id' => $this->getId()
        ];
        
        $propArray = array_merge($defaults, $propertyValues);
        $props = '';
        foreach ($propArray as $p => $v) {
            $props .= $p . '="' . $v . '" ';
        }
        
        return '<input type="' . $type . '" ' . $props . ' />';
    }
}