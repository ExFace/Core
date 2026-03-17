<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

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
    
    /**
     * Returns the relation path from the object of the parent sheet to the object of the subsheet.
     * 
     * Returns NULL if the objects are not related.
     * 
     * @return MetaRelationPathInterface|NULL
     */
    public function getRelationPathFromParentSheet() : ?MetaRelationPathInterface;
    
    /**
     * Returns TRUE if there is a relation path between the objects of the the parent 
     * sheet and the subsheet and FALSE otherwise.
     * 
     * @return bool
     */
    public function hasRelationToParent() : bool;
    
    /**
     * Returns the relation path from the object of the subsheet to the object of the parent sheet.
     * 
     * Returns NULL if the objects are not related.
     * 
     * @return MetaRelationPathInterface|NULL
     */
    public function getRelationPathToParentSheet() : ?MetaRelationPathInterface;

    /**
     * Returns alls deferred aggregations for this sheet. 
     * 
     * Deferred aggregations signify that some data in this sheet SHOULD be aggregated, but to allow for more
     * flexibility in JOINing this data, the actual process of aggregating it has not been performed yet. This will
     * usually be done automatically, when JOINing, but you can perform it manually via `aggregateLike()`.
     * 
     * @return array
     * @see DataSheetInterface::aggregateLike()
     */
    public function getDeferredAggregations() : array;

    /**
     * Add a deferred aggregation to this sub-sheet.
     * 
     * @param string              $columnName
     * @param AggregatorInterface $deferredAggregation
     * @return void
     */
    public function addDeferredAggregation(string $columnName, AggregatorInterface $deferredAggregation) : void;
}