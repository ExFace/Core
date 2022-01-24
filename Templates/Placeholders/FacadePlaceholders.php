<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\TemplateRenderer\Traits\PrefixedPlaceholderTrait;

/**
 * Resolves placeholders to facade propertis: `~facade:property`.
 * 
 * Technically this resolver calls the getter method of the property - e.g.
 * `~facade:theme` is resolved by calling `getTheme()` on the facade.
 *
 * @author Andrej Kabachnik
 */
class FacadePlaceholders implements PlaceholderResolverInterface
{
    use PrefixedPlaceholderTrait;
    
    private $prefix = null;
    
    private $facade = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(FacadeInterface $facade, string $prefix = '~facade:')
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
            $option = $this->stripPrefix($placeholder, $this->prefix);
            $methodName = 'get' . StringDataType::convertCaseUnderscoreToPascal($option);
            if (method_exists($this->facade, $methodName)) {
                $val = call_user_func([$this->facade, $methodName]);
            } else {
                throw new RuntimeException('Unknown placehodler "' . $placeholder . '" found in template!');
            }
            $vals[$placeholder] = $val;
        }
        return $vals;
    }
}