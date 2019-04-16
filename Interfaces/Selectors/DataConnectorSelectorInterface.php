<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for data connector selectors.
 * 
 * A connector is the prototype for a metamodel connection.
 * 
 * A connector can be identified by
 * - file path to a PHP class file
 * - qualified class name of the app's PHP class
 * 
 * @see DataConnectionSelectorInterface for selectors of metamodel connections
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataConnectorSelectorInterface extends PrototypeSelectorInterface
{}