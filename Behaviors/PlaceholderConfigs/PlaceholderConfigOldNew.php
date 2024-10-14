<?php

namespace exface\Core\Behaviors\PlaceholderConfigs;

use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Templates\Placeholders\DataRowPlaceholders;

/**
 * Adds support for the placeholders `~old:` and `~new:`.
 */
class PlaceholderConfigOldNew extends AbstractPlaceholderConfig
{
    public const PREFIX_OLD = '~old:';
    public const PREFIX_NEW = '~new:';
    
    protected static array $config = [
        OnBeforeCreateDataEvent::class => [
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],

        OnCreateDataEvent::class => [
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],

        OnBeforeUpdateDataEvent::class => [
            self::PREFIX_OLD => DataRowPlaceholders::class,
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],

        OnUpdateDataEvent::class => [
            self::PREFIX_OLD => DataRowPlaceholders::class,
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],

        OnBeforeDeleteDataEvent::class => [
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],

        OnDeleteDataEvent::class => [
            self::PREFIX_NEW => DataRowPlaceholders::class,
        ],
    ];
    
    public static function apply(
        TemplateRendererInterface &$renderer, 
        EventInterface $event,
        DataSheetInterface $dataSheetOld = null,
        DataSheetInterface $dataSheetNew = null,
        int $rowIndex = -1) : void
    {
        if($rowIndex < 0) {
            return;
        }
        
        if($dataSheetOld !== null && 
            static::isValidResolver(DataRowPlaceholders::class, self::PREFIX_OLD, $event)) {
            $resolver = new DataRowPlaceholders($dataSheetOld, $rowIndex, self::PREFIX_OLD);
            $resolver->setSanitizeAsUxon(true);
            $renderer->addPlaceholder($resolver);
        }

        if($dataSheetNew !== null &&
            static::isValidResolver(DataRowPlaceholders::class, self::PREFIX_NEW, $event)) {
            $resolver = new DataRowPlaceholders($dataSheetNew, $rowIndex, self::PREFIX_NEW);
            $resolver->setSanitizeAsUxon(true);
            $renderer->addPlaceholder($resolver);
        }
    }
}