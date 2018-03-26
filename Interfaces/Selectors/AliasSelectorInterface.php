<?php
namespace exface\Core\Interfaces\Selectors;

/**
 * Interface for selectors based on meta model aliases.
 * 
 * @author Andrej Kabachnik
 *
 */
interface AliasSelectorInterface extends SelectorInterface
{
    const ALIAS_NAMESPACE_DELIMITER = '.';
    
    /**
     * Returns TRUE if this selector is based on an alias and FALSE otherwise.
     * 
     * @return boolean
     */
    public function isAlias();
    
    /**
     *
     * @return string
     */
    public function getAppAlias() : string;
    
    /**
     * 
     * @return string
     */
    public function getVendorAlias() : string;
    
    /**
     *
     * @return AppSelectorInterface
     */
    public function getAppSelector() : AppSelectorInterface;
}