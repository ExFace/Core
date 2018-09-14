<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors of model components with prototypes (e.g. base PHP classes 
 * customized via UXON).
 * 
 * Many model components like actions, behaviors, etc. require a prototype class,
 * that defines the options, that can be set in the model. To tell the model, which
 * prototype should be used for a specific component, it's qualified class name or
 * file path can be used.
 * 
 * Referencing a prototype by class is very convenient within the source code:
 * just use the static constant ::class available in every PHP class definition
 * - e.g. new ActionSelector(exface\Core\Actions\ReadData::class)
 * 
 * Using the file path, on the other hand, is easier in the UI as various data
 * connectors for the file system can easily provide it.
 * 
 * @see ClassSelectorInterface
 * @see FileSelectorInterface
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