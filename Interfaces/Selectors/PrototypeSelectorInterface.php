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
     * Returns the normalized path (with "/" separators!) from app folder to the folder with prototype classes.
     * 
     * E.g. for action selectors, the subfolder would be "Actions" because action prototypes
     * are to be placed under APPFOLDER/Actions.
     * 
     * @return string
     */
    public function getPrototypeSubfolder() : string;
    
    /**
     * 
     * @param string $path
     * @return PrototypeSelectorInterface
     */
    public function setPrototypeSubfolder(string $path) : PrototypeSelectorInterface;
    
    /**
     * Returns the namespace of prototype classes relative to the app namespace.
     * 
     * This is the namespace version of getPrototypeSubfolder()
     * 
     * @return string
     */
    public function getPrototypeSubNamespace() : string;    
    
    /**
     * Returns the selector type specific suffix for class names.
     *
     * E.g. "App" for app selectors to make sure all app classes end with
     * "App" like "CoreApp".
     *
     * @return string
     */
    public function getPrototypeClassnameSuffix() : string;
    
    /**
     * 
     * @param string $string
     * @return PrototypeSelectorInterface
     */
    public function setPrototypeClassnameSuffix(string $string) : PrototypeSelectorInterface;
    
    /**
     * Returns the fully qualified class name of the prototype PHP class
     * 
     * @return string
     */
    public function getPrototypeClass() : string;
    
    /**
     * Returns TRUE if the prototype class exists and FALSE otherwise
     * 
     * @return boolean
     */
    public function prototypeClassExists() : bool;
    
    /**
     * Returns the selector for the app, that contains the prototype.
     * 
     * @return AppSelectorInterface
     */
    public function getPrototypeAppSelector() : AppSelectorInterface;
}