<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\Model\Aggregator;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\AggregatorInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Aggregate all values of a from-sheet column and write the result to every row of a to-sheet column.
 * 
 * Configure `from` and `to` expressions as for a regular column mapping. Set `aggregator` explicitly
 * for formulas and other expressions without a default aggregate function.
 * 
 * ```
 * 
 * {
 *     "from": "ORDER_VALUE",
 *     "to": "TOTAL_VALUE",
 *     "aggregator": "SUM"
 * }
 * 
 * ```
 * 
 * @see DataColumnMapping
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnAggregationMapping extends DataColumnMapping {
    
    private ?string $aggregatorString = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $fromExpr = $this->getFromExpression();
        $toExpr = $this->getToExpression();
        $logbook?->addLine("Column `{$fromExpr->__toString()}` aggregated via");

        $aggr = $this->getAggregator();
        $logbook?->continueLine(" `{$aggr->__toString()}` -> `{$toExpr->__toString()}`.");
        
        $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr);
        if ($fromCol === null) {
            throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Cannot aggregate from column "' . $fromExpr->toString() . '": there is no matching column in the from-data!', null, null, $logbook);
        }

        $toCol = $toSheet->getColumns()->getByExpression($toExpr);
        if (! $toCol) {
            $toCol = $toSheet->getColumns()->addFromExpression($toExpr);
        }
        $value = $fromCol->aggregate($aggr);
        if ($toSheet->isEmpty()) {
            $toCol->setValue(0, $value);
        } else {
            $toCol->setValueOnAllRows($value);
        }
        
        return $toSheet;
    }
    
    /**
     * 
     * @return AggregatorInterface
     */
    public function getAggregator() : AggregatorInterface
    {
        if ($this->aggregatorString !== null) {
            return new Aggregator($this->getWorkbench(), $this->aggregatorString);
        }
        $fromExpr = $this->getFromExpression();
        if ($fromExpr->isMetaAttribute() && $defaultAggregator = $fromExpr->getAttribute()->getDefaultAggregateFunction()) {
            return new Aggregator($this->getWorkbench(), $defaultAggregator);
        }
        throw new DataMappingConfigurationError($this, 'Cannot determine aggregator for column aggregation mapping: no explicit aggregator configured and the from-expression has no default aggregate function!');
    }

    /**
     * Select the function used to aggregate the from-column values.
     * 
     * If omitted, the default aggregate function of the `from` attribute is used.
     * 
     * @uxon-property aggregator
     * @uxon-type metamodel:aggregator
     * 
     * @param string $aggregator
     * @return DataColumnAggregationMapping
     */
    public function setAggregator(string $aggregator) : DataColumnAggregationMapping
    {
        $this->aggregatorString = $aggregator;
        return $this;
    }
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return DataSheetMapperSchema::class;
    }
}