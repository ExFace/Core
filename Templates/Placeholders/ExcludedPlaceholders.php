<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 * Excludes placeholders with a certain prefix from rendering (they remain untouched in the rendered template)
 * 
 * This can be usefull if multiple renderers are chained and every preceding renderer
 * must ignore placeholders inteded for the subsequent renderers.
 *
 * @author Andrej Kabachnik
 */
class ExcludedPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    private $prefix = null;
    
    private $before = '';
    
    private $after = '';
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(string $prefix = '~exclude:', string $delimiterBefore = '[#', string $delimiterAfter = '#]')
    {
        $this->prefix = $prefix;
        $this->before = $delimiterBefore;
        $this->after = $delimiterAfter;
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
            $vals[$placeholder] = $this->before . $placeholder . $this->after;
        }
        return $vals;
    }
}