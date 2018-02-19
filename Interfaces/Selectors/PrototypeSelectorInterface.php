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
    public function getPrototypeSubfolder();
    
    /**
     * Returns the namespace of prototype classes relative to the app namespace.
     * 
     * This is the namespace version of getPrototypeSubfolder()
     * 
     * @return string
     */
    public function getPrototypeSubNamespace();
    
    /**
     * Returns the fully qualified class name of the prototype PHP class
     * 
     * @return string
     */
    public function getPrototypeClass();
    
    /**
     * Returns TRUE if the prototype class exists and FALSE otherwise
     * 
     * @return boolean
     */
    public function prototypeClassExists();
}