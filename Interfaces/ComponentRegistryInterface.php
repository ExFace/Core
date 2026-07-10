<?php

namespace exface\Core\Interfaces;

use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;

interface ComponentRegistryInterface extends WorkbenchDependantInterface
{
    /**
     * @return string[]
     */
    public function getComponentKeys() : array;

    /**
     * @param string $component
     * @param string $selector
     * @return MarkdownPrinterInterface|null
     */
    public function getDocsForSelector(string $component, string $selector) : ?string;
}