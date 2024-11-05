<?php

namespace exface\Core\Templates\Placeholders;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class OptionalDataRowPlaceholder extends OptionalPlaceholders
{
    public function __construct(?DataSheetInterface $data, int $rowIndex, string $prefix, string $context = '')
    {
        $this->innerConstructor = function () use ($data, $rowIndex, $prefix) {
            if($data) {
                $resolver = new DataRowPlaceholders($data, $rowIndex, $prefix);
                $resolver->setSanitizeAsUxon(true);
                
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