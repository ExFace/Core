<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;

/**
 * 
 * ## Usage:
 * 
 * ### CSS
 * 
 * Add the following CSS to the style sheet (change slightly if required)
 * 
 * ```
    .exf-progressbar {width: 100%; padding: 0 3px; border:1px solid #ccc; position:relative; overflow: hidden; white-space:nowrap; color:transparent; box-sizing: border-box;}
    .exf-progressbar-bar {position: absolute; left:0; top:0;}
    .exf-progressbar-text {position:absolute; left:0; top:0; z-index:100; padding:0 2px; width:100%; color:initial; overflow: hidden; text-overflow: ellipsis;}
 * ```
 * @method \exface\Core\Widgets\ProgressBar getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait HtmlProgressBarTrait
{
    use JqueryAlignmentTrait;
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlProgressBar($value = null, string $text = null, $progress = null, string $color = null) : string
    {
        $widget = $this->getWidget();
        $style = '';
        if (! $widget->getWidth()->isUndefined()) {
            $style .= 'width:' . $this->getWidth() . '; ';
        }
        if (! $widget->getHeight()->isUndefined()) {
            $style .= 'height: ' . $this->getHeight() . '; ';
        }
        
        if ($text === null) {
            $text = $value ?? '&nbsp;';
            $titleProp = '';
        } else {
            $titleProp = 'title=' . json_encode($text);
        }
        $progress = $progress ?? $widget->getMin();
        $color = $color ?? 'transparent';
        
        $output = <<<HTML

<div id="{$this->getId()}" class="exf-progressbar" style="{$style}" {$titleProp}>{$text}
    <div class="exf-progressbar-bar" style="width:{$progress}%; background:{$color};">&nbsp;</div>
    <div class="exf-progressbar-text" style="text-align: {$this->buildCssTextAlignValue($widget->getAlign())}">{$text}</div>
</div>

HTML;
        return $output;
    }
    
    /**
     * 
     * {@inheritdoc}
     * @see AbstractJqueryElement::buildJsValueSetter()
     */
    public function buildJsValueSetter($value_js, $disable_formatting = false)
    {
        return "$('#{$this->getId()}').replaceWith($({$this->buildJsValueDecorator($value_js)}))";
    }
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        $widget = $this->getWidget();
        
        // The color map is presented as an array of arrays in JS because an object does not
        // retain the order of keys, which is crucial in this case.
        $colorMapJs = '';
        $colorMapStringBased = $widget->getValueDataType() instanceof StringDataType ? true : false;
        foreach ($widget->getColorScale() as $val => $color) {
            $colorMapJs .= '[' . ($colorMapStringBased || ! is_null($val) ? "'$val'" : $val) . ',  "' . $color . '"],';
        }
        $colorMapJs = rtrim($colorMapJs, ",");
        
        $textMapJs = json_encode($widget->getTextScale());
        $tpl = json_encode($this->buildHtmlProgressBar('exfph-val', 'exfph-text', 'exfph-progress', 'exfph-color'));
        $semanticColors = ($this->getFacade() instanceof AbstractAjaxFacade) ? $this->getFacade()->getSemanticColors() : [];
        $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
        
        return <<<JS
function() {
    var val = {$value_js};
    
    if (val === undefined || val === null || val === '') return '';

    var colorMap = [ {$colorMapJs} ];
    var textMap = {$textMapJs};
    var html = {$tpl};
    var numVal = parseFloat(val);    
    var color = colorMap[colorMap.length-1][1] || 'transparent';
    var oSemanticColors = $semanticColorsJs;

    var c = [];
    for (var i in colorMap) {
        c = colorMap[i];
        if (numVal <= c[0]) {
            color = c[1];
            break;
        }
    }

    if (oSemanticColors[color] !== undefined) {
        color = oSemanticColors[color];
    }
    
    html = html
        .replace(/exfph-val/g, val)
        .replace("exfph-progress", ((numVal / {$widget->getMax()} - {$widget->getMin()}) * 100))
        .replace("exfph-color", color);

    if (textMap.length > 0) {
        html = html.replace(/exfph-text/g, textMap[val]);
    } else {
        var text = {$this->buildJsValueFormatter('val')};
        if (text === undefined || text === null) {
            text = '&nbsp;';
        }
        html = html.replace(/exfph-text/g, text);
    }
    
    return html;
}()
JS;
    }
}
?>