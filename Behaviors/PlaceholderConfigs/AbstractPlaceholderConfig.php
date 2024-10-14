<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

/**
 * Placeholder configs allow for the fast and simple extension of behaviors with new
 * placeholders. They take care of validation and proper instantiation of resolvers.
 */
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
     *              'placeholderA' => SomeResolverClass::class,
     *              'placeholderB' => SomeResolverClass::class,
     *              ...
     *          ]
     *      ];
     * }
     *
     * ```
     *
     * @return array
     */  
    protected static array $config;
    
    /**
     * Checks whether a resolver is valid for a given event context.
     *
     * @param string         $resolverType
     * @param string         $placeholder
     * @param EventInterface $event
     * @return bool
     */
    protected static function isValidResolver(
        string $resolverType, 
        string $placeholder, 
        EventInterface $event) : bool
    {
        foreach (static::getConfigForEvent($event) as $placeholderToCheck => $resolverTypeToCheck) {
            if($placeholder === $placeholderToCheck &&
                $resolverType === $resolverTypeToCheck) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds all relevant placeholders from this config to the specified renderer.
     * 
     * @param TemplateRendererInterface $renderer
     * @param EventInterface            $event
     * @return void
     */
    public static abstract function apply(
        TemplateRendererInterface &$renderer, 
        EventInterface $event) : void;

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