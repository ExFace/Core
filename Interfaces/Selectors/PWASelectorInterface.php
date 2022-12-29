<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for progresive web apps (PWA).
 * 
 * A PWA can be identified by
 * - fully qualified alias (with app namespace if part of an app)
 * - file path or qualified class name of the app's PHP class
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWASelectorInterface extends AliasSelectorWithOptionalNamespaceInterface, UidSelectorInterface
{}