<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;

/**
 * Resolves placeholders to facade propertis: `~facade:property`.
 * 
 * Technically this resolver calls the getter method of the property - e.g.
 * `~facade:theme` is resolved by calling `getTheme()` on the facade.
 * 
 * Common placeholders:
 * - `~facade:theme`
 * - `~facade:file_version_hash`
 *
 * @author Andrej Kabachnik
 */
class FacadePlaceholders extends AbstractPlaceholderResolver
{
    private $facade = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(FacadeInterface $facade, string $prefix = '~facade:')
    {
        $this->setPrefix($prefix);
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
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $option = $this->stripPrefix($placeholder);
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