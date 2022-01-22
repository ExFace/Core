<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors for message channels.
 * 
 * A channel can be identified by
 * - fully qualified alias (with vendor and app prefix)
 * - file path or qualified class name of the app's PHP class (if there is one)
 * 
 * @see DataConnectorSelectorInterface for selectors of the channel prototypes
 * 
 * @author Andrej Kabachnik
 *
 */
interface CommunicationChannelSelectorInterface extends AliasSelectorInterface, PrototypeSelectorInterface
{}