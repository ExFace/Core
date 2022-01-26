<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\Facades\HtmlPageFacadeInterface;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 * Resolves placeholders to properties of the current UI page: `~page:property`.
 * 
 * Examples:
 * 
 * - `~page:alias` - the qualified alias
 * - `~page:url` - the URL to the page (only if a facade was provided as constructor argument!)
 * - `~page:title`
 * 
 * Technically this resolver calls the getter method of the property - e.g.
 * `~page:title` is resolved by calling `getTitle()` on the page.
 *
 * @author Andrej Kabachnik
 */
class UiPagePlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    private $prefix = null;
    
    private $page = null;
    
    private $facade = null;
    
    /**
     * 
     * @param UiPageInterface $page
     * @param HtmlPageFacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(UiPageInterface $page, HtmlPageFacadeInterface $facade, string $prefix = '~page:')
    {
        $this->prefix = $prefix;
        $this->page = $page;
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
            $property = $this->stripPrefix(mb_strtolower($placeholder), $this->prefix);
            switch (true) {
                case $property === 'alias':
                    $val = $this->page->getAliasWithNamespace();
                    break;
                case $property === 'url' && $this->facade !== null:
                    $val = $this->facade->buildUrlToPage($this->page);
                    break;
                default:
                    $method = 'get' . StringDataType::convertCaseUnderscoreToPascal($property);
                    if (method_exists($this->page, $method)) {
                        $val = call_user_func([$this->page, $method]);
                    } else {
                        throw new RuntimeException('Unknown placehodler "' . $placeholder . '" found in template!');
                    }
            }
            $vals[$placeholder] = $val;
        }
        return $vals;
    }
}