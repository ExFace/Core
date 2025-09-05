<?php
namespace exface\Core\Interfaces\TemplateRenderers;

/**
 * Modifiers are applied to placeholder values after they have been resolved
 * 
 * Once a placeholder resolver finds a value for a placeholder, one or more modifiers can be applied to that value.
 * Typical use cases would be
 * 
 * - escaping strings
 * - dealing with empty values
 * - custom formatting
 * 
 * The exact syntax depends on the template renderer. Many templating engines offer simplified ways to handle
 * placeholder values: e.g. filters in twig. The logic of the modifier is independent of exact syntax. Modifiers
 * aim to provide common functionality to different template renderes.
 * 
 * Bracket-hash templates used by the workbench by default will use a pipe symbol to apply modifiers: e.g.
 * `[#FIELD|htmlentitites#]` or `[#FIELD|??default_value#]`.
 * 
 * @author Andrej Kabachnik
 */
interface PlaceholderModifierInterface
{
    /**
     * Apply the filter to the already resolved value of the placeholder
     * 
     * @param $value
     * @return mixed
     */
    public function apply($value);
}