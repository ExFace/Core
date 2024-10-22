<?php

namespace exface\Core\Interfaces\TemplateRenderers;

use exface\Core\Behaviors\PlaceholderValidation\TemplateValidator;

/**
 * Can validate whether a given placeholder prefix is valid in a context.
 * 
 * PrefixValidators are most useful, when injected into a TemplateValidator.
 * 
 * @see TemplateValidator
 */
interface PrefixValidatorInterface
{
    /**
     * Checks, whether a given prefix is valid in the specified context, like
     * an event for example.
     * 
     * @param string $prefix
     * @param        $context
     * @return bool
     * Returns TRUE if the prefix is valid and FALSE otherwise.
     */
    public function isValidPrefixForContext(string $prefix, $context) : bool;
}