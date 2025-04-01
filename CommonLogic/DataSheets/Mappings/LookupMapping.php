<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
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
 * 	        "lookup_object_alias": "exface.Core.OBJECT",
 * 	        "lookup": "UID",
 *          "to": "UID",
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
    private MetaObjectInterface|null $lookupObject = null;
    
    private ExpressionInterface|null $lookupExpression = null;
    
    private ExpressionInterface|null $toExpression = null;
    
    private bool $createRowInEmptyData = true;

    private UxonObject|null $requiredMatchesUxon = null;
    
    private array $requiredMatchesCached = [];

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, DataSheetInterface $toSheet, LogBookInterface $logbook = null) : DataSheetInterface
    {
        $lookupExpr = $this->getLookupExpression();
        $toExpr = $this->getToExpression();
        
        $log = "Lookup `{$lookupExpr->__toString()}` -> `{$toExpr->__toString()}`.";

        $lookupSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getLookupObject());
        $lookupColumns = $lookupSheet->getColumns();
        $lookupColumns->addFromExpression($lookupExpr);
        $requiredMatches = $this->getRequiredMatches();

        foreach ($requiredMatches as $match) {
            $lookupColumns->addFromExpression($match['lookup']);
            // TODO pre-filter to improve performance?
        }

        $lookupSheet->dataRead();
        $rowsToMatch = $lookupSheet->getRows();

        foreach ($toSheet->getRows() as $rowNr => $toRow) {
            $matchData = [];
            foreach ($requiredMatches as $matchAliases) {
                $matchData[$matchAliases['lookup']] = $toRow[$matchAliases['to']];
            }
            
            $matchingRows = $this->findMatchingRows($matchData, $rowsToMatch);
            if(count($matchingRows) === 1) {
                // TODO This is probably not how expressions are used...
                $toSheet->setCellValue($toExpr->getAttribute()->getAlias(), $rowNr, $matchingRows[0][$lookupExpr->getAttribute()->getAlias()]);
            } else {
                // TODO Multiple matches
            }
        }

        $logbook?->addLine($log);
        
        return $toSheet;
    }
    
    protected function findMatchingRows(array $matchData, array $rowsToCheck) : array
    {
        $result = [];
        
        foreach ($rowsToCheck as $rowToCheck) {
            $valid = true;
            
            foreach ($matchData as $alias => $value) {
                if( !key_exists($alias, $rowToCheck) ||
                    $value !== $rowToCheck[$alias]) {
                    $valid = false;
                    break;
                }
            }
            
            if($valid) {
                $result[] = $rowToCheck;
            }
        }
        
        return $result;
    }

    /**
     * Returns an array of required matches - each providing pairs of expressions in the from-sheet and in the
     * lookup-sheet
     */
    protected function getRequiredMatches() : array
    {
        if( empty($this->requiredMatchesUxon) ||
            !$this->requiredMatchesUxon->isArray()) {
            return [];
        }
        
        if(!empty($this->requiredMatchesCached)) {
            return $this->requiredMatchesCached;
        }

        $result = [];

        $lookupObject = $this->getLookupObject();
        $toObject = $this->getMapper()->getToMetaObject();
        $workBench = $this->getWorkbench();

        foreach ($this->requiredMatchesUxon as $match) {
            $result[] = [
                'lookup' => ExpressionFactory::createFromString($workBench, $match['lookup'], $lookupObject),
                'to' => ExpressionFactory::createFromString($workBench, $match['to'], $toObject),
            ];
        }
        
        $this->requiredMatchesCached = $result;
        return $result;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::getRequiredExpressions()
     */
    public function getRequiredExpressions(DataSheetInterface $dataSheet) : array
    {
        $expressions = [];
        foreach ($this->getRequiredMatches() as $match) {
            $expressions[] = $match['to'];
        }
        return $expressions;
    }

    public function getLookupExpression() : ExpressionInterface
    {
        return $this->lookupExpression;
    }

    public function setLookupExpression(ExpressionInterface $expression) : LookupMapping
    {
        if ($expression->isReference()){
            throw new DataMappingConfigurationError($this, 'Cannot use widget links as expressions in data mappers!');
        }
        $this->lookupExpression = $expression;
        return $this;
    }
    
    /**
     * The attribute (or formula) to look up in the lookup-object and place into the to-column
     *
     * @uxon-property lookup_output
     * @uxon-type metamodel:expression
     */
    public function setLookupOutput($string) : LookupMapping
    {
        $expr = ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getLookupObject());
        $this->setLookupExpression($expr);
        return $this;
    }
    
    public function getLookupObject() : MetaObjectInterface|null
    {
        if(empty($this->lookupObject)) {
            throw new DataMappingConfigurationError($this, 'Missing value for "lookup_object_alias"!');
        }
        
        return $this->lookupObject;
    }

    /**
     * @uxon-property lookup_object_alias
     * @uxon-type metamodel:object
     * 
     * @param string $value
     * @return $this
     */
    public function setLookupObjectAlias(string $value) : LookupMapping
    {
        return $this->setLookupMetaObject($this->getWorkbench()->model()->getObject($value));
    }
    
    public function setLookupMetaObject(MetaObjectInterface $object) : LookupMapping
    {
        $this->lookupObject = $object;
        return $this;
    }

    public function getToExpression() : ExpressionInterface
    {
        return $this->toExpression;
    }

    public function setToExpression(ExpressionInterface $expression) : LookupMapping
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
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setTo()
     */
    public function setTo($string) : DataMappingInterface
    {
        $expr = ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getToMetaObject());
        $this->setToExpression($expr);
        return $this;
    }

    /**
     *
     * @return bool
     */
    public function getCreateRowInEmptyData() : bool
    {
        return $this->createRowInEmptyData;
    }

    /**
     * Set FALSE to prevent static expressions and formulas from adding rows to empty data sheets.
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
    public function setCreateRowInEmptyData(bool $value) : LookupMapping
    {
        $this->createRowInEmptyData = $value;
        return $this;
    }

    protected function setMatch(UxonObject $uxon) : LookupMapping
    {
        $this->requiredMatchesUxon = $uxon;
        return $this;
    }
}