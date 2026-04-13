<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Widgets\Markdown;

/**
 * This trait adds some configuration options to widget, that render Mermaid.js diagrams.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iRenderMermaidTrait {
    
    private $renderMermaidDiagrams = false;

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
    public function setRenderMermaidDiagrams(bool $value) : WidgetInterface
    {
        $this->renderMermaidDiagrams = $value;
        return $this;
    }
}