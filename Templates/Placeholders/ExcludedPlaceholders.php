<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Excludes placeholders with a certain prefix from rendering (they remain untouched in the rendered template)
 * 
 * This can be usefull if multiple renderers are chained and every preceding renderer
 * must ignore placeholders inteded for the subsequent renderers.
 *
 * @author Andrej Kabachnik
 */
class ExcludedPlaceholders extends AbstractPlaceholderResolver
{
    private $before = '';
    
    private $after = '';
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(string $prefix = '~exclude:', string $delimiterBefore = '[#', string $delimiterAfter = '#]')
    {
        $this->setPrefix($prefix);
        $this->before = $delimiterBefore;
        $this->after = $delimiterAfter;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders, ?LogBookInterface $logbook = null) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $vals[$placeholder] = $this->before . $placeholder . $this->after;
        }
        return $vals;
    }
}