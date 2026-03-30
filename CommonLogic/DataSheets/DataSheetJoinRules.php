<?php

namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

/**
 * Defines a set of rules for joining two data-sheets.
 */
class DataSheetJoinRules
{
    private ?string $leftKeyColumnName;
    private ?string $rightKeyColumnName;
    private ?MetaRelationPathInterface $relationPathFromLeftSheet;
    private array $aggregatorsPerColumn = [];

    /**
     * @param string|null                    $leftKeyColumnName
     * The name of the column on the LEFT side, which contains the keys that will be joined ON.
     * @param string|null                    $rightKeyColumnName
     * The name of the column on the RIGHT side, which contains the keys that will be joined FROM.
     * @param MetaRelationPathInterface|null $relationPathFromLeftSheet
     * @param null|array{
     *          columnName:string,
     *          aggregators:AggregatorInterface[]
    }                                        $aggregatorsPerColumn
     * An optional list of aggregators per column that will only be applied, if the join requires an aggregateLike.
     * This occurs, when the LEFT key column is aggregated, while the RIGHT is not. Then, the joined sheet will contain
     * one column per aggregator in this array. If you didn't specify any aggregators, the key column will be aggregated
     * with `LIST_DISTINCT`.
     */
    function __construct(
        ?string                   $leftKeyColumnName = null,
        ?string                   $rightKeyColumnName = null,
        MetaRelationPathInterface $relationPathFromLeftSheet = null,
        ?array                    $aggregatorsPerColumn = null
    )
    {
        $this->leftKeyColumnName = $leftKeyColumnName;
        $this->rightKeyColumnName = $rightKeyColumnName;
        $this->relationPathFromLeftSheet = $relationPathFromLeftSheet;
        
        if(!empty($aggregatorsPerColumn)){
            foreach ($aggregatorsPerColumn as $column => $aggregators) {
                if(!is_array($aggregators)) {
                    $aggregators = [$aggregators];
                }

                $this->addAggregatorsForColumn($column, $aggregators);
            }
        }
    }

    /**
     * The name of the column on the LEFT side, which contains the keys that will be joined ON.
     * 
     * @param string|null $leftKeyColumnName
     * @return void
     */
    public function setLeftKeyColumnName(?string $leftKeyColumnName): void
    {
        $this->leftKeyColumnName = $leftKeyColumnName;
    }

    /**
     * The name of the column on the LEFT side, which contains the keys that will be joined ON.
     * 
     * @return string|null
     */
    public function getLeftKeyColumnName() : ?string
    {
        return $this->leftKeyColumnName;
    }

    /**
     * The name of the column on the RIGHT side, which contains the keys that will be joined FROM.
     * 
     * @param string|null $rightKeyColumnName
     * @return void
     */
    public function setRightKeyColumnName(?string $rightKeyColumnName): void
    {
        $this->rightKeyColumnName = $rightKeyColumnName;
    }

    /**
     * The name of the column on the RIGHT side, which contains the keys that will be joined FROM.
     * 
     * @return string|null
     */
    public function getRightKeyColumnName(): ?string
    {
        return $this->rightKeyColumnName;
    }

    /**
     * @param MetaRelationPathInterface|null $relationPathFromLeftSheet
     * @return void
     */
    public function setRelationPathFromLeftSheet(?MetaRelationPathInterface $relationPathFromLeftSheet): void
    {
        $this->relationPathFromLeftSheet = $relationPathFromLeftSheet;
    }

    /**
     * @return MetaRelationPathInterface|null
     */
    public function getRelationPathFromLeftSheet() : ?MetaRelationPathInterface
    {
        return $this->relationPathFromLeftSheet;
    }

    /**
     * @param string                $columnName
     * @param AggregatorInterface[] $aggregators
     * @return $this
     */
    public function addAggregatorsForColumn(
        string $columnName,
        array $aggregators
    ) : DataSheetJoinRules
    {
        foreach ($aggregators as $aggregator) {
            $this->aggregatorsPerColumn[$columnName][$aggregator->exportString()] = $aggregator;
        }
        
        return $this;
    }

    /**
     * @return array
     */
    public function getAggregatorsPerColumn() : array
    {
        return $this->aggregatorsPerColumn;
    }
}