<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Exceptions\TemplateRenderer\PlaceholderValueInvalidError;

/**
 * Resolves case-sensitive server variable names from `$_SERVER`.
 *
 * For example, `[#~system:SQL_PASSWORD#]` reads `$_SERVER['SQL_PASSWORD']`, which can be
 * populated by Apache `SetEnv`. The variable must be available to the current PHP request.
 * Alternatively, `[#~env:SQL_PASSWORD#]` reads the environment variable using PHP's `getenv()`.
 * 
 * Depending on the PHP server integration, a variable may be available through both sources
 * or only one. Neither resolver falls back to the other source.
 * Apache `SetEnv` does not configure CLI processes; provide their variables separately.
 * Missing variables raise an error instead of resolving to an empty value.
 */
class SystemVariablePlaceholders extends AbstractPlaceholderResolver
{
    /**
     * Creates a resolver for server variables using the given placeholder prefix.
     *
     * @param string $prefix
     */
    public function __construct(string $prefix = '~system:')
    {
        $this->setPrefix($prefix);
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $result = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $key = $this->stripPrefix($placeholder);
            if (! array_key_exists($key, $_SERVER)) {
                throw new PlaceholderValueInvalidError($placeholder, 'Could not find server variable "' . $key . '". Make sure the variable is available in $_SERVER for this PHP request and is spelled correctly!');
            }
            $result[$placeholder] = $_SERVER[$key];
        }
        return $result;
    }
}
