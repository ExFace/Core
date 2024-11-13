<?php

namespace exface\Core\Templates\Placeholders;

use exface\Core\Behaviors\NotifyingBehavior;
use exface\Core\Behaviors\ValidatingBehavior;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

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
        $this->prefix = $prefix;
        
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

    /**
     *
     * @param callable $function
     * @return PlaceholderResolverInterface
     */
    public function setSanitizer(callable $function) : PlaceholderResolverInterface
    {
        if($resolver = $this->getInnerResolver()) {
            $resolver->setSanitizer($function);
        }
        
        return $this;
    }

    /**
     *
     * @param bool $value
     * @return PlaceholderResolverInterface
     */
    public function setSanitizeAsUxon(bool $value) : PlaceholderResolverInterface
    {
        if($resolver = $this->getInnerResolver()) {
            $resolver->setSanitizeAsUxon($value);
        }
        
        return $this;
    }
}