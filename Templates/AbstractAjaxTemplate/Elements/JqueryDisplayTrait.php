<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Display;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Templates\AbstractAjaxTemplate\Interfaces\JsValueDecoratingInterface;

/**
 *
 * @method Display getWidget()
 * @method Workbench getWorkbench()
 * 
 * @author Andrej Kabachnik
 *        
 */
trait JqueryDisplayTrait {

    /**
     * 
     * @return string
     */
    public function buildJs()
    {
        if ($this->hasFormatter()) {
            return $this->buildJsValueSetter($this->buildJsValueGetter()) . ';';
        }
        
        return '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value, $disable_formatting = false)
    {
        if (! $disable_formatting && $this->hasFormatter()) {
            $value = $this->buildJsValueFormatter($value);
        }
        
        return "$('#{$this->getId()}').html({$value})";
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return "$('#{$this->getId()}').html()";
    }
    
    /**
     * Returns inline JS code, that formats the given value.
     *
     * The result may be a function call or an immediately invoked anonymous function (IFEE).
     * NOTE: In any case, there is no ending semicolon!
     *
     * @param string $js_value
     * @return string
     */
    protected function buildJsValueFormatter($value_js)
    {
        return $this->getFormatter()->buildJsFormatter($value_js);
    }
    
    /**
     * Returns TRUE if this element requires a value formatter and FALSE otherwise.
     *
     * @return boolean
     */
    protected function hasFormatter()
    {
        
        return $this->buildJsValueFormatter('') !== '';
    }
    
    /**
     *
     * @return JsDataTypeFormatterInterface
     */
    protected function getFormatter()
    {
        return $this->getTemplate()->getDataTypeFormatter($this->getWidget()->getValueDataType());
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        return $this->buildJsValueFormatter($value_js);
    }
    
    /**
     * Returns TRUE if this element requires a value decorator and/or formatter and FALSE otherwise.
     *
     * @return boolean
     */
    public function hasDecorator()
    {
        return $this->buildJsValueDecorator('') !== '';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement::buildCssHeightDefaultValue()
     */
    protected function buildCssHeightDefaultValue()
    {
        return 'auto';
    }
    
    public function buildHtmlHeadTags()
    {
        return array_merge(parent::buildHtmlHeadTags(), $this->getFormatter()->buildHtmlBodyIncludes(), $this->getFormatter()->buildHtmlHeadIncludes());
    }
}
