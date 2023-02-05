<?php
namespace exface\Core\Interfaces\DataSheets;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataMatchInterface
{    
    public function getMatcher() : DataMatcherInterface;
    
    public function isUidMatch() : bool;
    
    public function getMainSheetPointer() : DataPointerInterface;
    
    public function getMatchedPointer() : DataPointerInterface;
    
    /**
     * 
     * @return string|number|NULL
     */
    public function getUid();
    
    /**
     * 
     * @return bool
     */
    public function hasUid() : bool;
}