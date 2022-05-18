<?php
namespace exface\Core\Widgets;

use cebe\markdown\GithubMarkdown;

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
        return $this->rebaseRelativeLinks(self::convertMarkdownToHtml($this->getMarkdown()));
    }

    /**
     * 
     * @param string $markdown
     * @return string
     */
    public static function convertMarkdownToHtml(string $markdown) : string
    {
        $parser = new GithubMarkdown();
        return $parser->parse($markdown);
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
}