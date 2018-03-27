<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on qualified class names.
 * 
 * Class-selectors are usefull to identify components based on PHP classes. Many
 * model components like actions, behaviors, etc. require a prototype class,
 * that defines the options, that can be set in the model.
 * 
 * Referencing a prototype by class is very convenient within the source code:
 * just use the static constant ::class available in every PHP class definition
 * - e.g. new ActionSelector(exface\Core\Actions\ReadData::class)
 * 
 * @see PrototypeSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
interface ClassSelectorInterface extends SelectorInterface
{    
    const CLASS_NAMESPACE_SEPARATOR = '\\';
    
    /**
     * Returns TRUE if this selector is based on a class name and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isClassname();
}