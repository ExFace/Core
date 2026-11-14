<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\DataTypes\ColorDataType;

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

    public function buildHtmlHeadTags()
    {
        $headers = parent::buildHtmlHeadTags();
        if ($this->getWidget()->getFill() && !$this->getWidget()->getColorOnly()) {
            $headers[] = '<script src="' . $this->getFacade()->buildUrlToVendorFile('exface/core/Facades/AbstractAjaxFacade/js/exfColorTools.js') . '"></script>';
        }
        return $headers;
    }
    
    /**
     * Returns the <img> HTML tag with the given source.
     * 
     * @param string $src
     * @return string
     */
    protected function buildHtmlIndicator(
        $value = null,
        string $text = null,
        string $hint = null,
        string $color = null
    ) : string
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
            if (!$widget->getColorOnly()) {
                $textColor = $color === 'exfph-color'
                    ? 'exfph-foreground'
                    : (!$color || $color === 'transparent' ? 'inherit' : ColorDataType::pickTextColorForBackgroundColor($color));
                $style .= " color: {$textColor} !important;";
            }
        } else {
            $style .= " font-weight: bold; color: {$color} !important;";
        }
        
        $color = $color ?? 'transparent';

        $title = $hint ? "title= \"{$hint}\"" : '';

        return <<<HTML

            <div id="{$this->getId()}" class="exf-colorindicator" {$title} style="{$style}">
                {$text}
            </div>
HTML;
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
     * @param string|null $rowJs Row data for color bindings to a different column.
     */
    public function buildJsValueDecorator($value_js, ?string $rowJs = null)
    {
        $tpl = json_encode(
            $this->buildHtmlIndicator('exfph-val', 'exfph-text', 'exfph-hint', 'exfph-color')
        );
        $semanticColors = ($this->getFacade() instanceof AbstractAjaxFacade) ? $this->getFacade()->getSemanticColors() : [];
        $semanticColorsJs = json_encode(empty($semanticColors) ? new \stdClass() : $semanticColors);
        $dataType = $this->getWidget()->getValueDataType();
        $jsEmptyText = $this->getFacade()->getDataTypeFormatter($dataType)->getJsEmptyText('null');
        $hintsJson = "{}";
        $textColorJs = $this->getWidget()->getFill() && !$this->getWidget()->getColorOnly()
            ? "(!sColor || sColor === 'transparent' ? 'inherit' : exfColorTools.pickTextColorForBackgroundColor(sColor))"
            : "'inherit'";

        if ($dataType instanceof EnumDataTypeInterface) {
            $hints = $dataType->getValueHints();
            $hintsJson = json_encode($hints);
        }
        
        return <<<JS
function() {
    var mValue = {$value_js};
    var sHtml = {$tpl};
    var sText = {$this->buildJsValueFormatter('mValue')};
    var oSemanticColors = $semanticColorsJs;
    let oHints = $hintsJson;
    
    if ((mValue === undefined || mValue === null) && ({$jsEmptyText} === null)) {
        return '';
    }

    let sHint = oHints[mValue] ? oHints[mValue] : '';

    if (sText === undefined || sText === null || sText === '') {
        sText = {$jsEmptyText} !== null ? {$jsEmptyText} : '&nbsp;';
    }
    
    var sColor = {$this->buildJsColorResolver('mValue', $rowJs)};
    
    if (oSemanticColors[sColor] !== undefined) {
       sColor = oSemanticColors[sColor];
    }

    return sHtml
        .replace(/exfph-mValue/g, mValue)
        .replace("exfph-color", sColor)
        .replace("exfph-foreground", {$textColorJs})
        .replace(/exfph-text/g, sText)
        .replace(/exfph-hint/g, sHint);
}()
JS;
    }
    
    /**
     * 
     * @param string $valueJs
     * @param string|null $rowJs
     * @return string
     */
    protected function buildJsColorResolver(string $valueJs, ?string $rowJs = null) : string
    {
        $widget = $this->getWidget();
        $binding = $widget->getColorBinding();
        $colorValueJs = null;
        if ($binding->isBoundToDataColumn()) {
            if ($binding->getDataColumnName() === $widget->getDataColumnName()) {
                $colorValueJs = $valueJs;
            } elseif ($rowJs !== null) {
                $columnJs = json_encode($binding->getDataColumnName());
                $colorValueJs = "({$rowJs} == null ? null : {$rowJs}[{$columnJs}])";
            }
        }
        if ($colorValueJs === null && !$binding->isBoundToDataColumn() && $binding->hasValue()) {
            $colorValueJs = json_encode($binding->getValue());
        }
        if ($widget->hasColorScale()) {
            return $this->buildJsScaleResolver($colorValueJs ?? $valueJs, $widget->getColorScale(), $widget->isColorScaleRangeBased());
        }
        return $colorValueJs !== null ? "({$colorValueJs} || 'transparent')" : "'transparent'";
    }
}