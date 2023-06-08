<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface PWADatasetInterface extends iCanBeConvertedToUxon
{
    public function getPWA() : PWAInterface;
    
    public function getMetaObject() : MetaObjectInterface;
    
    public function getDataSheet() : DataSheetInterface;
    
    public function getUid() : ?string;
    
    public function setUid(string $uid) : PWADatasetInterface;
    
    public function estimateRows() : ?int;
    
    public function readData(int $limit = null, int $offset = null, string $incrementValue = null) : DataSheetInterface;
    
    public function isIncremental() : bool;
    
    public function getIncrementAttribute() : ?MetaAttributeInterface;
    
    /**
     * 
     * @param DataSheetInterface $dataSheet
     * @return bool
     */
    public function canInclude(DataSheetInterface $dataSheet) : bool;
    
    /**
     * 
     * @param DataSheetInterface $anotherSheet
     * @return PWADatasetInterface
     */
    public function includeData(DataSheetInterface $anotherSheet) : PWADatasetInterface;
    
    /**
     *
     * @return string[]
     */
    public function getImageUrlDataTypeColumnNames() : array;
}