<?php

namespace exface\Core\Templates\Placeholders;

use exface\Core\Behaviors\NotifyingBehavior;
use exface\Core\Behaviors\ValidatingBehavior;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Functions just like DataRowPlaceholders, extended with some validation logic.
 * Most importantly, this wrapper can handle an empty datasheet, which means
 * you can use this as a context dependant resolver without further configuration.
 * 
 * @see ValidatingBehavior
 * @see NotifyingBehavior
 */
class OptionalDataRowPlaceholder extends OptionalPlaceholders
{
    public function __construct(?DataSheetInterface $data, int $rowIndex, string $prefix, string $context = '', bool $sanitizeAsUxon = false)
    {
        $this->innerConstructor = function () use ($data, $rowIndex, $prefix, $sanitizeAsUxon) {
            if($data) {
                $resolver = new DataRowPlaceholders($data, $rowIndex, $prefix);
                $resolver->setSanitizeAsUxon($sanitizeAsUxon);
                
                return  $resolver;
            }
            
            return null;
        };
        
        if($context === '') {
            $this->errorText = 'No data found to resolve instances of "'.$prefix.'"!';
        } else {
            $this->errorText = 'Placeholder "'.$prefix.'" not allowed for "'.$context.'"!';
        }
    }
}