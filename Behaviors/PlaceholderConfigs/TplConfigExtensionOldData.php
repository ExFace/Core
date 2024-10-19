<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;

/**
 * Adds support for the placeholders `~old:` and `~new:` prefixes.
 * 
 * ### Config
 * 
 * ```
 * 
 *  [
 *      OnBeforeCreateDataEvent::class => [
 *          self::PREFIX_NEW,
 *      ],
 * 
 *      OnCreateDataEvent::class => [
 *          self::PREFIX_NEW,
 *      ],
 * 
 *      OnBeforeUpdateDataEvent::class => [
 *          self::PREFIX_OLD,
 *          self::PREFIX_NEW,
 *      ],
 *  
 *      OnUpdateDataEvent::class => [
 *          self::PREFIX_OLD,
 *          self::PREFIX_NEW,
 *      ],
 *  
 *      OnBeforeDeleteDataEvent::class => [
 *          self::PREFIX_NEW,
 *      ],
 *  
 *      OnDeleteDataEvent::class => [
 *          self::PREFIX_NEW,
 *      ],
 *  ]
 * 
 * ```
 */
class TplConfigExtensionOldData extends AbstractTplConfigExtension
{
    public const PREFIX_OLD = '~old:';
    public const PREFIX_NEW = '~new:';
    
    public function getConfig() : array
    {
        return [
            OnBeforeCreateDataEvent::class => [
                self::PREFIX_NEW,
            ],

            OnCreateDataEvent::class => [
                self::PREFIX_NEW,
            ],

            OnBeforeUpdateDataEvent::class => [
                self::PREFIX_OLD,
                self::PREFIX_NEW,
            ],

            OnUpdateDataEvent::class => [
                self::PREFIX_OLD,
                self::PREFIX_NEW,
            ],

            OnBeforeDeleteDataEvent::class => [
                self::PREFIX_NEW,
            ],

            OnDeleteDataEvent::class => [
                self::PREFIX_NEW,
            ],
        ];
    }
    
    public function configureResolversForContext(
        string $context,
        array $resolvers, 
        TemplateRendererConfig $config) : array
    {
        $result = [];
        $configSettings = $config->extractContextSettings($context);
        
        foreach ($this->extractContextSettings($context) as $prefix) {
            if($this->appliesToPrefix($prefix, $configSettings)) {
                if($resolver = $this->findResolverWithPrefix($prefix, $resolvers)) {
                    $this->configureResolverForPrefix($prefix, $resolver);
                    $result[] = $resolver;
                }
            }
        }
        
        return $result;
    }

    protected function configureResolverForPrefix(string $prefix, PlaceholderResolverInterface &$resolver): void
    {
        if(!$resolver instanceof DataRowPlaceholders) {
            throw new InvalidArgumentException('Cannot configure resolver with prefix '.$prefix.': Its must be of type '.DataRowPlaceholders::class.'!');
        }
        
        $resolver->setSanitizeAsUxon(true);
    }


}