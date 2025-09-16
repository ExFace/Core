<?php

namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;

/**
 * Can resolve environment variable names (case-sensitive).
 * For example, `[#~env:USER#]` would resolve to the currently active system user-name.
 */
class EnvironmentVariablePlaceholders extends AbstractPlaceholderResolver
{
    function __construct(string $prefix = '~env:')
    {
        $this->setPrefix($prefix);
    }
    
    /**
     * @inheritDoc
     */
    public function resolve(array $placeholders): array
    {
        $result = [];
        $placeholders = $this->filterPlaceholders($placeholders);
        $envVars = getenv();
        
        foreach ($placeholders as $placeholder) {
            $key = $this->stripPrefix($placeholder);
            if(key_exists($key, $envVars)) {
                $result[$placeholder] = $envVars[$key];
            } else {
                throw new PlaceholderValueInvalidError($placeholder, 'Could not find environment variable "' . $key . '". Make sure the variable is defined on your system and is spelled correctly!');
            }
        }
        
        return $result;
    }
}