<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for mutation point selectors.
 * 
 * A mutation point can be identified by
 * - fully qualified class name (starting with a backslash)
 * - file name relative to the vendor folder (with forward slashes)
 * 
 * @author Andrej Kabachnik
 *
 */
interface MutationPointSelectorInterface extends PrototypeSelectorInterface
{}