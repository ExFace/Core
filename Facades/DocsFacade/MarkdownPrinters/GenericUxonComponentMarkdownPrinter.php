<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\CommonLogic\UxonObject;
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
    private const SENSITIVE_PROPERTY_NAME_TOKENS = [
        'token',
        'secret',
        'password',
        'pwd',
    ];

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
        $uxon = $this->component->exportUxonObject();
        $redactedUxon = UxonObject::fromArray($this->redactSensitiveProperties($uxon->toArray()));
        $uxonConfig = MarkdownDataType::escapeCodeBlock($redactedUxon->toJson(true));
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
     * Replaces values of sensitive UXON properties at every nesting level.
     *
     * A property is sensitive when its name contains one of the configured tokens, regardless of case.
     *
     * @param array $properties
     * @return array
     */
    private function redactSensitiveProperties(array $properties) : array
    {
        foreach ($properties as $propertyName => $value) {
            if (is_string($propertyName) && $this->isSensitiveProperty($propertyName)) {
                $properties[$propertyName] = '***';
            } elseif (is_array($value)) {
                $properties[$propertyName] = $this->redactSensitiveProperties($value);
            }
        }

        return $properties;
    }

    /**
     * Returns TRUE when a property name contains a sensitive token.
     *
     * @param string $propertyName
     * @return bool
     */
    private function isSensitiveProperty(string $propertyName) : bool
    {
        foreach (self::SENSITIVE_PROPERTY_NAME_TOKENS as $token) {
            if (stripos($propertyName, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public static function constructForInstance(object $instance): MarkdownPrinterInterface
    {
        return new self($instance);
    }
}