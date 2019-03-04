<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;

/**
 * A subsheet represents a part of a parent sheets data, that should be treated separately.
 * 
 * In addition to regular data sheet functionality, subsheets have a immutable link to
 * their parent sheet. They also contain "know" the key expressions needed to join their
 * data to the parent sheet.
 * 
 * @author Andrej Kabachnik
 *
 */
interface DataSheetSubsheetInterface extends DataSheetInterface
{
    /**
     *
     * @return DataSheetInterface
     */
    public function getParentSheet() : DataSheetInterface;

    /**
     * 
     * @return string
     */
    public function getJoinKeyAliasOfSubsheet() : string;
    
    /**
     *
     * @throws DataSheetColumnNotFoundError
     * @return DataColumnInterface
     */
    public function getJoinKeyColumnOfSubsheet() : DataColumnInterface;
    
    /**
     * 
     * @return string
     */
    public function getJoinKeyAliasOfParentSheet() : string;
    
    /**
     * 
     * @throws DataSheetColumnNotFoundError
     * @return DataColumnInterface
     */
    public function getJoinKeyColumnOfParentSheet() : DataColumnInterface;
    
}