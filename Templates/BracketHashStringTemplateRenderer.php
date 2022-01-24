<?php
namespace exface\Core\Templates;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\TemplateRenderer\Traits\BracketHashTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;

/**
 * Renderer for string templates using the standard `[##]` placeholder syntax.
 *
 * @author andrej.kabachnik
 *
 */
class BracketHashStringTemplateRenderer extends AbstractTemplateRenderer
{
    use BracketHashTemplateRendererTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     */
    public function render($tplString = null)
    {
        $tplString = $tplString ?? '';
        
        $phs = $this->getPlaceholders($tplString);
        $phVals = $this->getPlaceholderValues($phs);
        
        return $this->resolvePlaceholders($tplString, $phVals);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }
}