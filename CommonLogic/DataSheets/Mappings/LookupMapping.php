<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Interfaces\DataSheets\LookupMappingInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;

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
class LookupMapping extends AbstractDataSheetMapping implements LookupMappingInterface
{
    private $lookupExpression = null;
    
    private $toExpression = null;
    
    private $createRowInEmptyData = true;

    private $matchesUxon = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::getLookupExpression()
     */
    public function getLookupExpression()
    {
        return $this->lookupExpression;
    }

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::setLookupExpression()
     */
    public function setLookupExpression(ExpressionInterface $expression)
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
     * @uxon-property lookup_column
     * @uxon-type metamodel:expression
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataMappingInterface::setFrom()
     */
    public function setLookupColumn($string)
    {
        $this->setLookupExpression(ExpressionFactory::createFromString($this->getWorkbench(), $string, $this->getMapper()->getFromMetaObject()));
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::getToExpression()
     */
    public function getToExpression()
    {
        return $this->toExpression;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\LookupMappingInterface::setToExpression()
     */
    public function setToExpression(ExpressionInterface $expression)
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
    public function setTo($string)
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
        $fromExpr = $this->getLookupExpression();
        $toExpr = $this->getToExpression();
        
        $log = "Lookup `{$fromExpr->__toString()}` -> `{$toExpr->__toString()}`.";

        $lookupSheet = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $this->getLookupObjectAlias());
        $lookupSheet->getColumns()->addFromExpression($fromExpr);
        $lookupSheet->getFilters()->addConditionFromString(/* TODO */);
        $lookupSheet->dataRead();
        foreach ($lookupSheet->getRows() as $lookupRow) {
            /*
            Find matching row in to-sheet and add the looked up value into that row
            How to find the row??? 
            */
        }
        
        if ($logbook !== null) $logbook->addLine($log);
        
        return $toSheet;
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
    public function setCreateRowInEmptyData(bool $value) : LookupMapping
    {
        $this->createRowInEmptyData = $value;
        return $this;
    }

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
            $expressions[] = $match->getFromExpression();
        }
        return $expressions;
    }
}