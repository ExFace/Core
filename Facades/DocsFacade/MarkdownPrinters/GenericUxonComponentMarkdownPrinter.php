<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Facades\MarkdownInstancePrinterInterface;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;

/**
 * Prints some generic information about a component that can be configured via UXON
 * 
 * Includes alias, prototype class, current UXON configuration.
 */
class GenericUxonComponentMarkdownPrinter implements MarkdownInstancePrinterInterface
{
    private iCanBeConvertedToUxon $component;
    private int $headingLevel = 1;

    /**
     * @param iCanBeConvertedToUxon $component
     * @param int $headingLevel
     */
    public function __construct(iCanBeConvertedToUxon $component, int $headingLevel = 1)
    {
        $this->component = $component;
        $this->headingLevel = $headingLevel;
    }

    /**
     * Builds and returns the complete Markdown for the current component
     */
    public function getMarkdown(): string
    {
        $name = PhpClassDataType::findClassNameWithoutNamespace(get_class($this->component));
        $heading = MarkdownDataType::buildMarkdownHeader($name, $this->headingLevel);
        $uxonConfig = MarkdownDataType::escapeCodeBlock($this->component->exportUxonObject()->toJson(true));
        if (method_exists($this->component, 'getAliasWithNamespace')) {
            $alias = '`' . $this->component->getAliasWithNamespace() . '`';
        } else {
            $alias = '';
        }        
        $prototypeClass = '\\' . get_class($this->component);
        $prototypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($this->component);
        return <<<MD
{$heading}

- Alias: {$alias}
- Prototype: [$prototypeClass]($prototypeLink)

## UXON configuration

{$uxonConfig}
MD;
    }
    
    /**
     * @inheritDoc
     */
    public static function constructForInstance(object $instance): MarkdownPrinterInterface
    {
        return new self($instance);
    }
}