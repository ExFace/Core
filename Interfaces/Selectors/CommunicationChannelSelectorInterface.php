<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for message channels.
 * 
 * A channel can be identified by
 * - fully qualified alias (with app namespace if part of an app)
 * - file path or qualified class name of the app's PHP class
 * 
 * @see DataConnectorSelectorInterface for selectors of the channel prototypes
 * 
 * @author Andrej Kabachnik
 *
 */
interface CommunicationChannelSelectorInterface extends AliasSelectorWithOptionalNamespaceInterface, PrototypeSelectorInterface
{}