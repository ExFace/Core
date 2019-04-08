<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for widget selectors.
 * 
 * A widget can be identified by 
 * - widget type (short alias) for core widgets
 * - fully qualified alias (with vendor and app prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 *
 */
interface WidgetSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface
{}