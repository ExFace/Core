<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors with versions or version constraints
 * 
 * @author Andrej Kabachnik
 *
 */
interface VersionedSelectorInterface extends SelectorInterface
{
    const VERSION_SEPARATOR = ':';
    
    public function getVersion() : string;
    
    public function hasVersion() : bool;
}