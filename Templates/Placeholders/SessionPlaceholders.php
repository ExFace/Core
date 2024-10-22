<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Resolves placeholders to session values: `~session:language`.
 * 
 * Currently supported placeholders are:
 * 
 * - `~session:language`,
 * - `~session:locale`
 *
 * @author Andrej Kabachnik
 */
class SessionPlaceholders extends AbstractPlaceholderResolver
{
    private $workbench = null;
    
    /**
     * 
     * @param FacadeInterface $workbench
     * @param string $prefix
     */
    public function __construct(WorkbenchInterface $workbench, string $prefix = '~session:')
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
            $option = $this->stripPrefix($placeholder);
            switch (mb_strtolower($option)) {
                case 'language':
                case 'locale':
                    $locale = $this->workbench->getContext()->getScopeSession()->getSessionLocale();
                    if ($option === 'language') {
                        $val = explode('_', $locale)[0];
                    } else {
                        $val = $locale;
                    }
                    break;
                default:
                    $val = '';
            }
            $vals[$placeholder] = $val;
        }
        return $vals;
    }
}