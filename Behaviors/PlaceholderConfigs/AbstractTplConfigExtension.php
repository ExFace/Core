<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixedPlaceholderResolverInterface;

/**
 * A template renderer config extension can be used to add a set of predefined placeholders and their
 * associated rules and expectations to a `TemplateRendererConfig`.
 */
abstract class AbstractTplConfigExtension extends AbstractPhConfig
{
    /**
     * @inheritDoc
     */
    public abstract function getConfig(): array;

    /**
     * Configures the specified resolvers according to the config and discards
     * any illegal instances.
     *
     * @param PlaceholderResolverInterface[] $resolvers
     * @param string                         $context
     * @param TemplateRendererConfig         $config
     * @return PlaceholderResolverInterface[]
     */
    public abstract function configureResolversForContext(
        string $context,
        array $resolvers,
        TemplateRendererConfig $config) : array;

    /**
     * Configures a single resolver, depending on the prefix used.
     *
     * @param PlaceholderResolverInterface $resolver
     * @param string                       $prefix
     * @return void
     */
    protected abstract function configureResolverForPrefix(string $prefix, PlaceholderResolverInterface &$resolver) : void;

    /**
     * Returns the first instance in the array that matches the given prefix or FALSE if no match was found.
     * 
     * @param array  $resolvers
     * @param string $prefix
     * @return PlaceholderResolverInterface|null
     */
    protected function findResolverWithPrefix(string $prefix, array $resolvers) : PlaceholderResolverInterface | false
    {
        foreach ($resolvers as $resolver) {
            $resolverPrefix = $resolver instanceof PrefixedPlaceholderResolverInterface ? $resolver->GetPrefix() : '';
            if($prefix === $resolverPrefix) {
                return $resolver;
            }
        }
        
        return false;
    }
    
    /**
     * Check whether this config extension applies to a given prefix.
     * 
     * @param string $prefix
     * @param array  $contextSettings 
     * `[string $prefix => string $extensionType]`
     * The config settings for the context you wish to check.
     * @return bool
     */
    protected function appliesToPrefix(string $prefix, array $contextSettings) : bool {
        return static::class === $contextSettings[$prefix];
    }
}