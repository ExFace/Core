<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors of model objects with prototypes (e.g. base PHP classes customized vie UXON).
 * 
 * @author Andrej Kabachnik
 *
 */
interface PrototypeSelectorInterface extends ClassSelectorInterface, FileSelectorInterface
{    
    /**
     * Returns the selector for the app, that contains the prototype.
     * 
     * @return AppSelectorInterface
     */
    public function getPrototypeAppSelector() : AppSelectorInterface;
}