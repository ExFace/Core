<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Resolves placeholders to config values: `~config:app_alias:key`.
 * 
 * @author Andrej Kabachnik
 */
class ConfigPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
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
            $value = $this->stripPrefix($placeholder, $this->prefix);
            list($appAlias, $option) = explode(':', $value);
            $vals[$placeholder] = $this->workbench->getApp($appAlias)->getConfig()->getOption(mb_strtoupper($option));
        }
        return $vals;
    }
}