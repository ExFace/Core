<?php
namespace exface\Core\Widgets;

use cebe\markdown\GithubMarkdown;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

/**
 * Shows markdown contents rendered as HTML.
 * 
 * The markdown code can be 
 * 
 * - loaded from a data source (by specifying an `attribute_alias` for the widget)
 * - loaded from a file (by specifying the path in `file`)
 * - directly specified in the `html` property of the widget
 *
 * @author Andrej Kabachnik
 *        
 */
class Markdown extends Html
{
    private $renderMermaidDiagrams = false;
    private $openLinksIn = null;
    private $openLinksInPopupWidth = null;
    private $openLinksInPopupHeight = null;
    
    const OPEN_LINKS_IN_SELF = 'self';
    const OPEN_LINKS_IN_POPUP = 'popup';
    const OPEN_LINKS_IN_NEW_TAB = 'new_tab';
    
    
    /**
     * @return string $markdown
     */
    public function getMarkdown()
    {
        $md = $this->getValue();
        return $md === null ? '' : $md;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Html::getHtml()
     */
    public function getHtml(){
        return $this->rebaseRelativeLinks(MarkdownDataType::convertMarkdownToHtml($this->getMarkdown()));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Html::getCss()
     */
    public function getCss()
    {
        $css = <<<CSS

img {max-width: 100%}

CSS;
        $css .= parent::getCss();
        return $css;
    }
    
    /**
     * 
     * @return bool
     */
    public function hasRenderMermaidDiagrams() : bool
    {
        return $this->renderMermaidDiagrams;
    }
    
    /**
     * Set to TRUE to render ```mermaid blocks as diagrams
     * 
     * @uxon-property render_mermaid_diagrams
     * @uxon-type boolean
     * @uxon-default 
     * 
     * @param bool $value
     * @return Markdown
     */
    public function setRenderMermaidDiagrams(bool $value) : Markdown
    {
        $this->renderMermaidDiagrams = $value;
        return $this;
    }
    
    /**
     * This determines how hyperlinks will open when clicked.
     *  self: opens the hyperlink in the same window.
     *  popup: opens the hyperlink in popup.
     *  new_tab: opens the hyperlink in a new browser tab.
     * 
     * @uxon-property open_links_in
     * @uxon-type [self,new_tab,popup]
     * @uxon-defaul self
     * @uxon-template self
     * 
     * @param string $value
     * @return $this
     */
    public function setOpenLinksIn(string $value) : Markdown
    {
        $constName = 'static::OPEN_LINKS_IN_' . strtoupper($value);
        if (!defined($constName)) {
                throw new WidgetConfigurationError($this, 'Invalid value: ' . $value . '. Only self, popup or new_tab are allowed!' );
        } 
            
        $this->openLinksIn = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOpenLinksIn() : ?string
    {
        return $this->openLinksIn;
    }

    /**
     * Sets the width of the popup window. Only works with "$open_links_in: popup" and the "open_links_in_popup_height" property.
     *
     * @uxon-property open_links_in_popup_width
     * @uxon-type number
     * @uxon-defaul 1200
     *
     * @param number $value
     * @return Markdown
     */
    public function setOpenLinksInPopupWidth($value) : Markdown
    {
        $this->openLinksInPopupWidth = $value;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOpenLinksInPopupWidth() : ?int {
        return $this->openLinksInPopupWidth;
    }

    /**
     * Sets the height of the popup window. Only works with "$open_links_in: popup" and the "open_links_in_popup_width" property.
     *
     * @uxon-property open_links_in_popup_height
     * @uxon-type number
     * @uxon-defaul 800
     *
     * @param number $value
     * @return Markdown
     */
    public function setOpenLinksInPopupHeight($value) : Markdown
    {
        $this->openLinksInPopupHeight = $value;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOpenLinksInPopupHeight() : ?int
    {
        return $this->openLinksInPopupHeight;
    }
}