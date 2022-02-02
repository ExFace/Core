<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

/**
 * Trait for string templates using the standard `[##]` placeholder syntax.
 * 
 * The main idea here is, that each resolver knows, which placeholders it can
 * resolve - e.g. by using unique placeholder prefixes as namespaces: ´[~data:xxx]´, 
 * `[#~config:xxx#]`, etc. Thus, non-prefixed placeholders will not be evaluated
 * while the regular resolvers are applied - this is where a default resolver can
 * be useful: pass a resolver with an empty namespace to `setDefaultPlaceholderResolver()`
 * and it will receive all the leftovers.
 * 
 * @author andrej.kabachnik
 *
 */
trait BracketHashTemplateRendererTrait
{    
    private $defaultResolver = null;
    
    private $ignoreUnknownPlaceholders = false;
    
    /**
     * 
     * @param string $tpl
     * @return string[]
     */
    protected function getPlaceholders(string $tpl) : array
    {
        return array_unique(StringDataType::findPlaceholders($tpl));
    }
    
    /**
     * 
     * @param string[] $placeholders
     * @return array
     */
    protected function getPlaceholderValues(array $placeholders) : array
    {
        $phVals = [];
        
        // Resolve regular placeholders
        foreach ($this->getPlaceholderResolvers() as $resolver) {
            $phVals = array_merge($phVals, $resolver->resolve($placeholders));
        }
        
        // If there are placeholders left without values, see if there is a default resolver
        // and let it render the missing placeholders
        if (count($phVals) < count($placeholders) && $defaultResolver = $this->getDefaultPlaceholderResolver()) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            $phVals = array_merge($phVals, $defaultResolver->resolve($missingPhs));
        }
        
        // If there are still missing placeholders, either reinsert them or raise an error
        if (count($phVals) < count($placeholders)) {
            $missingPhs = array_diff($placeholders, array_keys($phVals));
            if ($this->isIgnoringUnknownPlaceholders()) {
                foreach ($missingPhs as $ph) {
                    $phVals[$ph] = '[#' . $ph . '#]';
                }
            } else {
                throw new RuntimeException('Unknown placehodler(s) "[#' . implode('#]", "[#', $missingPhs) . '#]" found in template!');
            }
        }
        
        return $phVals;
    }
    
    /**
     * 
     * @param string $tplString
     * @param array $placeholderValues
     * @return string
     */
    protected function resolvePlaceholders(string $tplString, array $placeholderValues) : string
    {
        return StringDataType::replacePlaceholders($tplString, $placeholderValues, false);
    }
    
    /**
     * 
     * @return PlaceholderResolverInterface|NULL
     */
    protected function getDefaultPlaceholderResolver() : ?PlaceholderResolverInterface
    {
        return $this->defaultResolver;
    }
    
    /**
     * The default resolver will receive all the placeholders that are left after
     * all regular resolvers were run.
     * 
     * The main idea here is, that each resolver knows, which placeholders it can
     * resolve - e.g. by using unique placeholder prefixes as namespaces: ´[~data:xxx]´, 
     * `[#~config:xxx#]`, etc. Thus, non-prefixed placeholders will not be evaluated
     * while the regular resolvers are applied - this is where a default resolver can
     * be useful: pass a resolver with an empty namespace to `setDefaultPlaceholderResolver()`
     * and it will receive all the leftovers.
     * 
     * @param PlaceholderResolverInterface $value
     * @return BracketHashTemplateRendererTrait
     */
    public function setDefaultPlaceholderResolver(PlaceholderResolverInterface $value) : TemplateRendererInterface
    {
        $this->defaultResolver = $value;
        return $this;
    }
    
    protected function isIgnoringUnknownPlaceholders() : bool
    {
        return $this->ignoreUnknownPlaceholders;
    }
    
    public function setIgnoreUnknownPlaceholders(bool $value) : TemplateRendererInterface
    {
        $this->ignoreUnknownPlaceholders = $value;
        return $this;
    }
}