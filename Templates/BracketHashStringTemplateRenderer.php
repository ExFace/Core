<?php
namespace exface\Core\Templates;

use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\TemplateRenderer\Traits\BracketHashTemplateRendererTrait;
use exface\Core\CommonLogic\TemplateRenderer\AbstractTemplateRenderer;
use exface\Core\Exceptions\TemplateRenderer\TemplateRendererRuntimeError;

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
    public function render($tplString = null, ?LogBookInterface $logbook = null)
    {
        $tplString = $tplString ?? '';
        $logbook = $logbook ?? new DataLogBook('Template renderer');
        try {
            $phs = $this->getPlaceholders($tplString);
            $phVals = $this->getPlaceholderValues($phs, $logbook);
        } catch (\Throwable $e) {
            throw new TemplateRendererRuntimeError($this, 'Cannot render template. ' . $e->getMessage(), null, $e, $tplString);
        }
        
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