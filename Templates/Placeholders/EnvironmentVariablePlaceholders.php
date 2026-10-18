<?php

namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;

/**
 * Resolves case-sensitive environment variable names using PHP's `getenv()`.
 * 
 * For example, `[#~env:SQL_PASSWORD#]` reads the `SQL_PASSWORD` environment variable.
 * Alternatively, `[#~system:SQL_PASSWORD#]` reads the value from PHP's `$_SERVER` array,
 * for example when Apache `SetEnv` exposes it there.
 * 
 * Depending on the PHP server integration, a variable may be available through both sources
 * or only one. Neither resolver falls back to the other source. Missing variables raise an error.
 * Apache `SetEnv` does not configure CLI processes; provide their variables separately.
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