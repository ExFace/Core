<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for CMS connector selectors.
 * 
 * A CMS connector can be identified by 
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @author Andrej Kabachnik
 *
 */
interface CmsConnectorSelectorInterface extends PrototypeSelectorInterface
{}