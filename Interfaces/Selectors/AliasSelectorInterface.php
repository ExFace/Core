<?php
namespace exface\Core\Interfaces\Selectors;

use exface\Core\Interfaces\AliasInterface;

/**
 * Interface for selectors based on meta model aliases.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AliasSelectorInterface extends SelectorInterface, AliasInterface
{
    const ALIAS_NAMESPACE_DELIMITER = '.';
    
    /**
     * Returns the alias of the app (first and second parts of the namespace)
     * 
     * @return string
     */
    public function getAppAlias();
    
    /**
     * Returns the selector for the app responsible for the selected component
     * 
     * @return AppSelectorInterface
     */
    public function getAppSelector() : AppSelectorInterface;
    
    /**
     * Returns the alias of the vendor (first part of the namespace)
     * 
     * @return string
     */
    public function getVendorAlias();
    
    /**
     * Returns TRUE if this selector is based on an alias and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isAlias();
}