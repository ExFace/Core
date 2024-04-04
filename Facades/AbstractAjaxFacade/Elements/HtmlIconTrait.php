<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;

/**
 * This trait helps render icons as HTML elements - e.g. `<i class="fa fa-xxx"></i>`.
 * 
 * To use it, just use the trait in your element and call buildJsValueDecorator() where needed.
 * 
 * @see \exface\JEasyUIFacade\Facades\Elements\EuiIcon for an example
 * 
 * @method \exface\Core\Widgets\Icon getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlIconTrait
{
    use JsValueScaleTrait;
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        $widget = $this->getWidget();
        if ($widget->hasIconScale()) {
            $value_js = $this->buildJsScaleResolver($value_js, $this->getWidget()->getIconScale(), $widget->isIconScaleRangeBased());
        }
        return <<<JS
(function(sVal) {
    return '<i class="{$this->buildCssIconClass("")}' + sVal + '"></i>';
})($value_js)
JS;
    }
    
    /**
     * 
     * @return string
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-icon';
    }
}