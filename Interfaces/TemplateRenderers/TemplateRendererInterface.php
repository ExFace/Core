<?php
namespace exface\Core\Interfaces\TemplateRenderers;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\iCanBeCopied;

/**
 * Interface for classes, that render templates by replacing placeholders.
 * 
 * The main idea is to separate the parsing logic (finding placeholders) from resolving the
 * placeholder values. Different renderers (parsers) can be easily combined with any
 * placeholder resolvers. All the renderer needs to do is pass an array of placeholder
 * names to each resolver - regardless of what syntax the placeholders use or whether
 * the template itself is a string, a file, an object or whatever else - these things
 * are taken care by the renderer only!
 * 
 * @author andrej.kabachnik
 *
 */
interface TemplateRendererInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon, iCanBeCopied
{
    public function render();
    
    public function addPlaceholder(PlaceholderResolverInterface $resolver) : TemplateRendererInterface;
}