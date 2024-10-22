<?php

namespace exface\Core\Behaviors\PlaceholderValidation;

use exface\Core\Interfaces\Events\DataChangeEventInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\TemplateRenderers\PrefixValidatorInterface;

class PrefixValidatorOldData implements PrefixValidatorInterface
{
    public const PREFIX_OLD = '~old:';
    public const PREFIX_NEW = '~new:';

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