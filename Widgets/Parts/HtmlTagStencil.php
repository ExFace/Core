<?php
namespace exface\Core\Widgets\Parts;

use exface\Core\Interfaces\Widgets\WidgetPartInterface;

/**
 * TODO
 * 
 * @author Andrej Kabachnik
 *
 */
class HtmlTagStencil extends TextStencil
{   
    private ?string $htmlTag = null;
    private ?string $cssStyle = null;

    /**
     * @return bool
     */
    public function isHtmlTag(): bool
    {
        return $this->htmlTag !== null;
    }
    
    /**
     * @return string|null
     */
    public function getHtmlTag() : ?string
    {
        return $this->htmlTag;
    }

    /**
     * Render the template as this HTML tag
     * 
     * @uxon-property html_tag
     * @uxon-type string
     * @uxon-template div
     * 
     * @param string $tag
     * @return WidgetPartInterface
     */
    protected function setHtmlTag(string $tag) : WidgetPartInterface
    {
        $this->htmlTag = $tag;
        return $this;
    }

    /**
     * @return string|null
     */
    public function buildCssStyle() : ?string
    {
        return $this->cssStyle;
    }

    /**
     * Custom CSS style for the outer HTML element of the stencil
     * 
     * @uxon-property css_style
     * @uxon-type string
     * 
     * @param string $style
     * @return WidgetPartInterface
     */
    protected function setCssStyle(string $style) : WidgetPartInterface
    {
        $this->cssStyle = $style;
        return $this;
    }
}