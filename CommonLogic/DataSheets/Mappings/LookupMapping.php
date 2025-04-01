<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 * Looks up a value in a separate data sheet and places it in the to-column
 * 
 * ## Examples
 * 
 * {
 *   "from_object_alias": "exface.Core.ATTRIBUTE",
 *   "to_object_alias": "exface.Core.OBJECT",
 *   "lookup_mappings": [
 * 	    {
 * 	        "to": "UID",
 * 	        "lookup_object_alias": "exface.Core.OBJECT",
 * 	        "lookup_column": "UID",
 * 	        "match": [
 * 		        {
 * 		            "from": "OBJECT__ALIAS",
 * 		            "lookup": "ALIAS"
 * 		        }
 * 	        ]
 * 	    }
 *   ]
 * }
 * 
 * @author Andrej Kabachnik
 *
 */
class LookupMapping extends AbstractDataSheetMapping
{
    private $lookupObjectAlias = null;
    private $lookupObject = null;
    private $lookupExpressionString = null;
    private $lookupExpression = null;
    private $toExpression = null;
    private $createRowInEmptyData = true;
    private $matchesUxon = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::getLookupExpression()
     */
    protected function getLookupExpression() : ExpressionInterface
    {
        if ($this->lookupExpression === null) {
            $lookupObj = $this->getLookupObject();
            switch (true) {
                case $this->lookupExpressionString !== null:
                    $expr = ExpressionFactory::createForObject($lookupObj, $this->lookupExpressionString);
                    break;
                case $lookupObj->hasUidAttribute():
                    $expr = ExpressionFactory::createForObject($lookupObj, $lookupObj->getUidAttributeAlias());
                    break;
                default:
                    throw new DataMappingConfigurationError($this, 'Missing "lookup_column" in the configuration of a lookup mapping');
            }
            if ($expr->isReference()){
                throw new DataMappingConfigurationError($this, 'Cannot use widget links as expressions in data mappers!');
            }
            $this->lookupExpression = $expr;
        }
        return $this->lookupExpression;
    }
    
    /**
     * The attribute (or formula) to look up in the lookup-object and place into the to-column
     * 
     * If not set explicitly, the UID of the lookup object will be used
     * 
     * @uxon-property lookup_column
     * @uxon-type metamodel:expression
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setFrom()
     */
    protected function setLookupColumn($string) : LookupMapping
    {
        $this->lookupExpressionString = $string;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::getToExpression()
     */
    protected function getToExpression() : ExpressionInterface
    {
        return $this->toExpression;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::setToExpression()
     */
    protected function setToExpression(ExpressionInterface $expression) : LookupMapping
    {
        if ($expression->isReference()){
            throw new DataMappingConfigurationError($this, 'Cannot use widget links as expressions in data mappers!');
        }
        $this->toExpression = $expression;
        return $this;
    }
    
    /**
     * This is the expression where the lookup values are going to be placed to
     * 
     * @uxon-property to
     * @uxon-type metamodel:expression
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setTo()
     */
    protected function setTo($string) : LookupMapping
    {
        $this->setToExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getToMetaObject()));
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null)
    {
        $lookupExpr = $this->getLookupExpression();
        $toExpr = $this->getToExpression();
        
        $log = "Lookup `{$lookupExpr->__toString()}` -> `{$toExpr->__toString()}`.";

        $matches = $this->getMatches();
        $lookupSheet = DataSheetFactory::createFromObject($this->getLookupObject());
        $lookupCol = $lookupSheet->getColumns()->addFromExpression($lookupExpr);
        foreach ($matches as $match) {
            $lookupSheet->getColumns()->addFromExpression($match['lookup']);
        }
        foreach($matches as $match) {
            $fromExpr = ExpressionFactory::createForObject($fromSheet->getMetaObject(), $match['from']);
            if (! $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr)) {
                throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Missing column "' . $match['from'] . '" in from-data for a lookup mapping!');
            }
            $lookupSheet->getFilters()->addConditionFromValueArray($match['lookup'], $fromCol->getValues());
        }
        $lookupSheet->dataRead();

        // See if the target column will be a subsheet. We need to create column slightly differently
        // for subsheets (the attribute_alias is the reverse relation) and for regular columns 
        // (attribute_alias points to the specific attribute)
        $isSubsheet = false;
        if (! $toCol = $toSheet->getColumns()->getByExpression($toExpr)) {
            if ($this->needsSubsheet($toExpr)) {
                $isSubsheet = true;
                $toCol = $toSheet->getColumns()->addFromExpression($toExpr->getAttribute()->getRelationPath()->getRelationFirst()->getAlias());
            } else {
                $toCol = $toSheet->getColumns()->addFromExpression($toExpr);
            }
        }

        $lookupColName = $lookupCol->getName();
        $toColVals = [];
        $toValDelim = $toExpr->getAttribute()->getValueListDelimiter();
        // For every row in the from-sheet we will create a row in the to-sheet
        foreach ($fromSheet->getRows() as $i => $fromRow) {
            $toColVals[$i] = null;
            // Look for matching lookup rows for this from-row
            foreach ($lookupSheet->getRows() as $lookupRow) {
                $prevVal = $toColVals[$i];
                // If any of the keys DO NOT match, continue with next lookup row
                foreach ($matches as $match) {
                    $matchVal = $lookupRow[$match['lookup']];
                    $fromVal = $fromRow[$match['from']];
                    if ($matchVal !== $fromVal) {
                        continue 2;
                    }
                }
                // Now we have the value we were looking for
                $lookupVal = $lookupRow[$lookupColName];
                // If it belongs into a subsheet, make sure the value is saved as an array representation
                // of a data sheet. Otherwise save it as-is.
                if ($isSubsheet) {
                    if (! is_array($prevVal)) {
                        $toColVals[$i] = [
                            'object_alias' => $toExpr->getAttribute()->getRelationPath()->getEndObject()->getAliasWithNamespace(),
                            'rows' => []
                        ];
                    }
                    if ($lookupVal !== null && $lookupVal !== '') {
                        foreach (explode($toValDelim, $lookupVal) as $val) {
                            $toColVals[$i]['rows'][] = [$toExpr->getAttribute()->getAlias() => $val];
                        }
                    }
                } else {
                    if ($prevVal === null) {
                        $toColVals[$i] = $lookupVal;
                    } else {
                        throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Lookup returned more than 1 value on row ' . $i);
                    }
                }
            }
        }
        $toCol->setValues($toColVals);
        
        if ($logbook !== null) $logbook->addLine($log);
        
        return $toSheet;
    }

    protected function needsSubsheet(ExpressionInterface $expr) : bool
    {
        if (! $expr->isMetaAttribute()) {
            return false;
        }
        $attr = $expr->getAttribute();
        $relPath = $attr->getRelationPath();
        if ($relPath->isEmpty()) {
            return false;
        }
        if ($relPath->countRelations() > 1) {
            return false;
        }
        if (! $relPath->getRelationFirst()->isReverseRelation()) {
            return false;
        }
        return true;
    }

    /**
     * The meta object to read when looking up values
     * 
     * @uxon-property lookup_object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @param string $aliasOrUid
     * @return LookupMapping
     */
    protected function setLookupObjectAlias(string $aliasOrUid) : LookupMapping
    {
        $this->lookupObjectAlias = $aliasOrUid;
        return $this;
    }

    protected function getLookupObject() : MetaObjectInterface
    {
        if ($this->lookupObject === null) {
            if ($this->lookupObjectAlias === null) {
                throw new DataMappingConfigurationError($this, 'Missing "lookup_object_alias" in the configuration of a lookup mapping');
            }
            $this->lookupObject = MetaObjectFactory::createFromString($this->getWorkbench(), $this->lookupObjectAlias);
        }
        return $this->lookupObject;
    }
    
    /**
     *
     * @return bool
     */
    protected function getCreateRowInEmptyData() : bool
    {
        return $this->createRowInEmptyData;
    }
    
    /**
     * Set to FALSE to prevent static expressions and formulas from adding rows to empty data sheets.
     * 
     * A static from-expression like `=Today()` applied to an empty to-sheet will normally
     * add a new row with the generated value. This option can explicitly disable this behavior
     * for a single mapping. There is also a similar global setting `inherit_empty_data` for
     * the entire mapper. 
     *
     * @uxon-property create_row_in_empty_data
     * @uxon-type bool
     *
     * @param bool $value
     * @return LookupMapping
     */
    protected function setCreateRowInEmptyData(bool $value) : LookupMapping
    {
        $this->createRowInEmptyData = $value;
        return $this;
    }

    /**
     * Pairs of attribtues to match when searching lookup data: attribute from the from-sheet + attribute of the lookup object
     * 
     * @uxon-property matches
     * @uxon-type metamodel:attribute[]
     * @uxon-template [{"from": "", "lookup": ""}]
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return LookupMapping
     */
    protected function setMatches(UxonObject $uxon) : LookupMapping
    {
        $this->matchesUxon = $uxon;
        return $this;
    }

    /**
     * Returns an array of matches - each providing pairs of expressions in the from-sheet and in the lookup-sheet
     * @return void
     */
    protected function getMatches() : array
    {
        if ($this->matchesUxon === null) {
            return [];
        }
        return $this->matchesUxon->toArray();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        $expressions = [];
        foreach ($this->getMatches() as $match) {
            $expressions[] = ExpressionFactory::createForObject($this->getMapper()->getFromMetaObject(), $match['from']);;
        }
        return $expressions;
    }
}