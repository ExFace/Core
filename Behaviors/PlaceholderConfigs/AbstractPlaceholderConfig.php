<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixedPlaceholderResolverInterface;

abstract class AbstractPlaceholderConfig
{
    /**
     * An array that defines what placeholders will be resolved for a given
     * event context. It serves as a whitelist, meaning that only explicitly defined placeholders
     * are going to work.
     *
     * Override this method to adapt it to the needs of your behavior. Adhere to the following structure:
     *
     * ```
     *
     * protected function getConfig() : array
     * {
     *      return [
     *        SomeEventClass::class => [
     *              'prefixA' => SomeResolverClass::class,
     *              'prefixB' => SomeResolverClass::class,
     *              ...
     *          ]
     *      ];
     * }
     *
     * ```
     *
     * @return array
     */
    public abstract function getConfig() : array;
    
    public abstract function apply(PlaceholderResolverInterface $resolver) : bool;


    /**
     * Checks whether a resolver is valid for a given event context.
     *
     * @param PlaceholderResolverInterface $resolver
     * @param EventInterface               $event
     * @return bool
     */
    protected static function isValidResolver(
        PlaceholderResolverInterface $resolver,
        EventInterface $event) : bool
    {
        $resolverType = get_class($resolver);
        $prefix = '';
        if($resolver instanceof  PrefixedPlaceholderResolverInterface) {
            $prefix = $resolver->GetPrefix();
        }

        foreach (static::getConfigForEvent($event) as $prefixToCheck => $resolverTypeToCheck) {
            if($prefix === $prefixToCheck &&
                $resolverType === $resolverTypeToCheck) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge another config with the rules of this one.
     *
     * @param array $config
     * @return array
     */
    public static function mergeConfigs(array $config) : array
    {
        return array_merge($config, static::$config);
    }

    /**
     * Returns a list of legal placeholders for a given event. Override
     * `getPlaceholderConfig()` to define what placeholders will be used for
     * different event types.
     *
     * @param EventInterface $event
     * @return array
     */
    public static function getConfigForEvent(EventInterface $event) : array
    {
        $key = get_class($event);
        if(!key_exists($key, static::$config)) {
            return [];
        }

        return static::$config[$key];
    }
}