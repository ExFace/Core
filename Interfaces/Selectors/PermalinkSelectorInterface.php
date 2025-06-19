<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for permalinks.
 * 
 * A channel can be identified by
 * - fully qualified alias (with app namespace)
 * - file path or qualified class name of the app's PHP class
 * 
 * @author Andrej Kabachnik
 *
 */
interface PermalinkSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface
{}