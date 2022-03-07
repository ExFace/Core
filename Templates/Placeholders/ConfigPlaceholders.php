<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\SanitizedPlaceholderTrait;

/**
 * Resolves placeholders to config values: `~config:app_alias:key`.
 * 
 * @author Andrej Kabachnik
 */
class ConfigPlaceholders implements PlaceholderResolverInterface
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
    public function __construct(WorkbenchInterface $workbench, string $prefix = '~config:')
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
            list($appAlias, $option) = explode(':', $phStripped);
            $val = $this->workbench->getApp($appAlias)->getConfig()->getOption(mb_strtoupper($option));
            $vals[$placeholder] = $this->sanitizeValue($val);
        }
        return $vals;
    }
}