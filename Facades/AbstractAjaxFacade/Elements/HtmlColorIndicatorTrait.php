<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;

/**
 *
 * @method \exface\Core\Widgets\ColorIndicator getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlColorIndicatorTrait
{
    use JqueryAlignmentTrait;
    use JsValueScaleTrait;
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlIndicator($value = null, string $text = null, string $color = null) : string
    {
        $widget = $this->getWidget();
        $style = 'box-sizing: border-box;';
        
        if (! $widget->getWidth()->isUndefined()) {
            $style .= ' width:' . $this->getWidth() . '; ';
        } elseif ($widget->getFill()) {
            $style .= ' width: 100%;';
        } else {
            $style .= ' width: 100%;';
        }
        
        if (! $widget->getHeight()->isUndefined()) {
            $style .= ' height: ' . $this->getHeight() . '; ';
        } else {
            $style .= ' height: 100%;';
        }
        
        if ($text === null) {
            $text = $value ?? '&nbsp;';
        }
        
        if ($widget->getColorOnly()) {
            $text = '&nbsp;';
        }
        
        if ($widget->getFill()) {
            $style .= " padding: 0 3px; background-color: {$color} !important;";
        } else {
            $style .= " font-weight: bold; color: {$color} !important;";
        }
        
        $color = $color ?? 'transparent';
        
        $output = <<<HTML

<div id="{$this->getId()}" class="exf-colorindicator" style="{$style}">
    {$text}
</div>

HTML;
        return $output;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value_js)
    {
        return "$('#{$this->getId()}').replaceWith($({$this->buildJsValueDecorator($value_js)}))";
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        $tpl = json_encode($this->buildHtmlIndicator('exfph-val', 'exfph-text', 'exfph-color'));
        return <<<JS
function() {
    var mValue = {$value_js};
    var sHtml = {$tpl};
    var sColor = 'transparent';
    var sText = {$this->buildJsValueFormatter('mValue')};

    if (mValue === undefined || mValue === null) {
        return '';
    }

    if (sText === undefined || sText === null || sText === '') {
        sText = '&nbsp;';
    }
 
    sColor = {$this->buildJsColorResolver('mValue')};

    return sHtml
        .replace(/exfph-mValue/g, mValue)
        .replace("exfph-color", sColor)
        .replace(/exfph-text/g, sText);
}()
JS;
    }
    
    /**
     * 
     * @param string $valueJs
     * @return string
     */
    protected function buildJsColorResolver(string $valueJs) : string
    {
        $widget = $this->getWidget();
        if ($widget->hasColorScale()) {
            return $this->buildJsScaleResolver('mValue', $widget->getColorScale(), $widget->isColorScaleRangeBased());
        }        
        return 'transparent';
    }
}