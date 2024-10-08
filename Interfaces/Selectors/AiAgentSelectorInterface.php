<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for AI agent selectors.
 * 
 * An agent can be identified by 
 * - fully qualified alias (with vendor and app prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 *
 */
interface AiAgentSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface
{}