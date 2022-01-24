<?php
namespace exface\Core\Templates;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\TemplateRenderer\Traits\BracketHashTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\Traits\FileTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;

/**
 * Renderer for template files using the standard `[##]` placeholder syntax.
 * 
 * @author andrej.kabachnik
 *
 */
class BracketHashFileTemplateRenderer extends AbstractTemplateRenderer
{
    use BracketHashTemplateRendererTrait;
    use FileTemplateRendererTrait;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     */
    public function render($tplPath = null)
    {
        $tplString = $this->getTemplate($tplPath);
        
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
        $uxon = new UxonObject();
        if ($val = $this->getTemplateFilePath()) {
            $uxon->setProperty('template_file_path', $val);
        }
        return $uxon;
    }
}