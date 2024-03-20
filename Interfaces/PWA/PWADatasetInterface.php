<?php
namespace exface\Core\Interfaces\PWA;

use exface\Core\Interfaces\DataSheets\DataColumnInterface;
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

    public function getIncrementalColumns() : ?array;

    public function getNonIncrementalColumns() : ?array;
    
    public function readData(int $limit = null, int $offset = null, string $incrementValue = null) : DataSheetInterface;
    
    /**
     * 
     * @return bool
     */
    public function isIncremental() : bool;

    /**
     * Check all necessary properties of the given relation column to see if it is incremental.
     *
     * @param MetaObjectInterface $dataSheetObject
     * @param DataColumnInterface $column
     * @param string $incrementAttributeAlias
     * @return bool
     */
    public function isIncrementalRelationColumn(
        MetaObjectInterface $dataSheetObject,
        DataColumnInterface $column,
        string $incrementAttributeAlias) : bool;
    
    /**
     * 
     * @return MetaAttributeInterface|NULL
     */
    public function getIncrementAttribute() : ?MetaAttributeInterface;
    
    /**
     * Returns the last increment value included in the given data assuming it is fresh.
     * 
     * Depending on the increment type, the logic is different:
     * 
     * - time-related increments will yield the current time if the data is considered fresh
     * and the latest timestamp from all timestamping columns (main object and related objects)
     * otherwise
     * - numeric increments (e.g. ids) will yield the the highest number in the data in any
     * case. In contrast to time-related increments, the numeric increment only concideres
     * a single column - e.g. the id of a log entry - and does not take relation into account.
     * 
     * @param DataSheetInterface $data
     * @param bool $assumeDataIsFresh
     * @return string|NULL
     */
    public function getIncrementValue(DataSheetInterface $data, bool $assumeDataIsFresh = true) : ?string;
    
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