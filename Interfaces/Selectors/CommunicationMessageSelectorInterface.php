<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for message prototypes in the communication framework.
 * 
 * Communication channels can work with different types of messages - their prototypes.
 * 
 * A message prototype can be identified by
 * - file path to a PHP class file
 * - qualified class name of the app's PHP class
 * 
 * @author Andrej Kabachnik
 *
 */
interface CommunicationMessageSelectorInterface extends PrototypeSelectorInterface
{}