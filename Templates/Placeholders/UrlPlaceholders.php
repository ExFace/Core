<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 * Resolves placeholders to URLs of UI pages: `~url:page_alias`.
 * 
 * If no page alias as given, the URL will point to the index page.
 *
 * @author Andrej Kabachnik
 */
class UrlPlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    private $prefix = null;
    
    private $facade = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(FacadeInterface $facade, string $prefix = '~url:')
    {
        $this->prefix = $prefix;
        $this->facade = $facade;
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
            $pageSelectorString = $this->stripPrefix($placeholder, $this->prefix);
            if ($pageSelectorString === '') {
                $val = $this->facade->buildUrlToSiteRoot();
            } else {
                $val = $this->facade->buildUrlToPage($pageSelectorString);
            }
            $vals[$placeholder] = $val;
        }
        return $vals;
    }
}