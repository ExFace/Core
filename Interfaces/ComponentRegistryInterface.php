<?php

namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Facades\MarkdownPrinterInterface;

/**
 * Interface for the central component registry, which allows to work consistently with all component types.
 * 
 * @author Andrej Kabachnik
 */
interface ComponentRegistryInterface extends WorkbenchDependantInterface
{
    /**
     * @return string[]
     */
    public function getComponentKeys(?string $havingKey = null) : array;

    /**
     * Returns the configured DataSheet template for saving the component type.
     *
     * @param string $component
     * @return UxonObject|null
     */
    public function getComponentSaveData(string $component) : ?UxonObject;

    /**
     * @param string $component
     * @param string $selector
     * @return MarkdownPrinterInterface|null
     */
    public function getDocsForSelector(string $component, string $selector) : ?string;

    /**
     * @param string $searchTerm
     * @param string $component
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSheetInterface
     */
    public function searchPrototypes(string $searchTerm, string $component, ?int $limit = null, int $offset = null) : DataSheetInterface;
}