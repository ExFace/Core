<?php
namespace exface\Core\Interfaces;

/**
 * Compares SQL schema dumps and returns human-readable difference lines.
 */
interface SqlSchemaComparatorInterface
{
    /**
     * Compares the current and previous schema dump and returns output lines.
     *
     * @param string $currentSchema
     * @param string $previousSchema
     * @return string[]
     */
    public function compare(string $currentSchema, string $previousSchema) : array;

    /**
     * Builds a structured tree of added and removed schema lines.
     *
     * @param string $currentSchema
     * @param string $previousSchema
     * @return array
     */
    public function buildTree(string $currentSchema, string $previousSchema) : array;
}