<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders containing translation keys: `~translate:app_alias:translation_key`.
 * 
 * E.g. `~translate:exface.Core:GLOBAL.MODEL.ACTION`
 *
 * @author Andrej Kabachnik
 */
class TranslationPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    use SanitizedPlaceholderTrait;
    
    private $prefix = null;
    
    private $workbench = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(WorkbenchInterface $workbench, string $prefix = '~translate:')
    {
        $this->prefix = $prefix;
        $this->workbench = $workbench;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders, $this->prefix) as $placeholder) {
            $phStripped = $this->stripPrefix($placeholder, $this->prefix);
            list($appAlias, $message) = explode(':', $phStripped);
            $val = $this->workbench->getApp($appAlias)->getTranslator()->translate(mb_strtoupper($message));
            $vals[$placeholder] = $this->sanitizeValue($val);
        }
        return $vals;
    }
}