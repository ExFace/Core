<?php
namespace exface\Core\Facades\DocsFacade\MarkdownPrinters;

use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Facades\DocsFacade;
use exface\Core\Interfaces\Facades\MarkdownInstancePrinterInterface;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;

/**
 * Builds a Markdown documentation view for a behavior instance.
 */
class BehaviorMarkdownPrinter extends AbstractMarkdownPrinter implements MarkdownInstancePrinterInterface
{
    private BehaviorInterface $behavior;
    private int $headingLevel = 1;

    /**
     * Creates a new behavior Markdown printer for the given behavior.
     */
    public function __construct(BehaviorInterface $behavior, int $headingLevel = 1)
    {
        $this->behavior = $behavior;
        $this->headingLevel = $headingLevel;
    }

    /**
     * {@inheritDoc}
     * @see MarkdownInstancePrinterInterface::constructForInstance()
     */
    public static function constructForInstance(object $instance) : MarkdownPrinterInterface
    {
        return new self($instance);
    }

    /**
     * Builds and returns the complete Markdown for the current behavior.
     */
    public function getMarkdown(): string
    {
        $behavior = $this->behavior;
        $heading = MarkdownDataType::buildMarkdownHeader($behavior->getName(), $this->headingLevel);
        $prototypeClass = '\\' . get_class($behavior);
        $prototypeLink = DocsFacade::buildUrlToDocsForUxonPrototype($behavior);
        $objectLink = DocsFacade::buildUrlToDocsForMetaObject($behavior->getMetaObject());

        return <<<MD

{$heading}

- Prototype: [$prototypeClass]($prototypeLink)
- Object: [{$behavior->getMetaObject()->__toString()}]({$objectLink})

```
{$behavior->exportUxonObject()->toJson(true)}
```
MD;
    }
}