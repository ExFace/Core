<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders to config values: `~config:app_alias:key`.
 * 
 * @author Andrej Kabachnik
 */
class ConfigPlaceholders extends AbstractPlaceholderResolver
{
    use SanitizedPlaceholderTrait;
    
    private $workbench = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(WorkbenchInterface $workbench, string $prefix = '~config:')
    {
        $this->setPrefix($prefix);
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
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $phStripped = $this->stripPrefix($placeholder);
            list($appAlias, $option) = explode(':', $phStripped);
            $val = $this->workbench->getApp($appAlias)->getConfig()->getOption(mb_strtoupper($option));
            $vals[$placeholder] = $this->sanitizeValue($val);
        }
        return $vals;
    }
}