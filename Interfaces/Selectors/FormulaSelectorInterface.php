<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for formula selectors.
 * 
 * A formula can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 *
 */
interface FormulaSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface
{}