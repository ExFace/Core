<?php

namespace exface\Core\Behaviors\PlaceholderValidation;

use exface\Core\Interfaces\Events\DataChangeEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixValidatorInterface;

/**
 * Can validate the prefixes `~old:` and `~new:`.
 * 
 * - `~old:` is only allowed when the context is an instance of `DataChangeEventInterface`.
 * - `~new:` is only allowed when the context is an instance of `DataSheetEventInterface`, which
 * includes `DataChangeEventInterface`.
 */
class PrefixValidatorOldData implements PrefixValidatorInterface
{
    public const PREFIX_OLD = '~old:';
    public const PREFIX_NEW = '~new:';

    /**
     * @param string $prefix
     * @param        $context
     * @return bool
     * - `~old:` Only TRUE if context is instance of `DataChangeEventInterface`
     * - `~new:` Only TRUE if context is instance of `DataSheetEventInterface`, which
     * includes `DataChangeEventInterface`.
     * - Any other Prefix: TRUE
     */
    public function isValidPrefixForContext(string $prefix, $context) : bool
    {
        switch ($prefix) {
            case self::PREFIX_OLD:
                return $context instanceof DataChangeEventInterface;
            case  self::PREFIX_NEW:
                return $context instanceof DataSheetEventInterface;
            default:
                return true;
        }
    }
}