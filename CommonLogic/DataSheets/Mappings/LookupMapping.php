<?php
namespace exface\Core\CommonLogic\DataSheets\Mappings;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;
use exface\Core\Interfaces\Model\ExpressionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingConfigurationError;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Uxon\DataSheetLookupMappingSchema;

/**
 * Looks up a value in a separate data sheet and places it in the to-column
 * 
 * This mapper looks up a value for each row in the from-sheet and writes this value into
 * the same row in the to-sheet. This means, it produces as many rows in the to-sheet as
 * there were in the from-sheet. 
 * 
 * You can think of it as a column-to-column mapping, which uses an additional step reading
 * a third data sheet (called `lookup`) and maps data from that lookup sheet to the to-sheet
 * instead of getting its values from the from-sheet directly.
 * 
 * ## Reading lookup data
 * 
 * This mapper searches for lookup values for the entire from-data. This means, there will be
 * one read operation per input DataSheet. This is fine for most actions, but may produce significant
 * overhead if there are a lot of separate mappings performed - e.g. when importing data row-by-row.
 * An alternative would be to `read_all` available data at once and do the lookups in-memory. This
 * will only conduct a single read operation, but will read all data of the lookup action.
 * 
 * ## Things to keep in mind
 * 
 * This mapping does not check, if the rows of the to-sheet are in the same order as those in
 * the from-sheet. In fact, it does not even understand if they are related at all - looks up
 * values in the lookup sheet for every row in the from-sheet and puts them to the to-row with 
 * the same number. 
 * 
 * ## Examples
 * 
 * ### Lookup a UID by name or alias
 * 
 * Consider having a data sheet of exface.Core.ATTRIBUTE, that includes object aliases, but not
 * their UIDs.
 * 
 * ```
 * {
 *   "from_object_alias": "exface.Core.ATTRIBUTE",
 *   "to_object_alias": "exface.Core.OBJECT",
 *   "lookup_mappings": [
 * 	    {
 * 	        "to": "OBJECT",
 * 	        "lookup_object_alias": "exface.Core.OBJECT",
 * 	        "matches": [
 * 		        {
 * 		            "from": "OBJECT__ALIAS",
 * 		            "lookup": "ALIAS"
 * 		        }
 * 	        ]
 * 	    }
 *   ]
 * }
 * 
 * ```
 * 
 * ### Create mappings by looking up related objects
 * 
 * ```
 * {
 *   "from_object_alias": "my.App.TASK_TEMPLATE",
 *   "to_object_alias": "my.App.TASK",
 *   "lookup_mappings": [
 * 	    {
 * 	        "to": "TASK_TAGS__TAG",
 * 	        "lookup_object_alias": "my.App.TAG",
 * 	        "lookup_column": "UID",
 * 	        "matches": [
 * 		        {
 * 		            "from": "TAGS",
 * 		            "lookup": "NAME"
 * 		        }
 * 	        ]
 * 	    }
 *   ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class LookupMapping extends AbstractDataSheetMapping
{
    public const IF_NOT_FOUND_LEAVE_EMPTY = 'leave_empty';
    public const IF_NOT_FOUND_ERROR_FIRST = 'error_on_first';
    public const IF_NOT_FOUND_ERROR_ALL = 'error_cumulative';
    public const IF_NOT_FOUND_FALLBACK = 'use_fallback_value';
    public const STOP_ON_FIRST_MISS = [
        self::IF_NOT_FOUND_ERROR_FIRST
    ];
    
    private $lookupObjectAlias = null;
    private $lookupObject = null;
    private $lookupExpressionString = null;
    private $lookupExpression = null;
    private $toExpression = null;
    private $createRowInEmptyData = true;
    private $matchesUxon = null;
    private $readAll = false;
    
    private $lookupCache = null;

    private $ignoreIfMissingFromColumn = false;
    private ?UxonObject $notFoundErrorUxon = null;
    private string $notFoundBehavior = 'leave_empty';
    private bool $stopOnFirstMiss = false;
    private ?string $fallbackValue = null;

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
        $this->clearCache();
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

        // See if the target column will be a subsheet. We need to create column slightly differently
        // for subsheets (the attribute_alias is the reverse relation) and for regular columns 
        // (attribute_alias points to the specific attribute)
        $isSubsheet = false;
        $isRequired = false;
        if (! $toCol = $toSheet->getColumns()->getByExpression($toExpr)) {
            if ($this->needsSubsheet($toExpr)) {
                $isSubsheet = true;
                // Currently there is no way to tell, if at least one row is required in a subsheet,
                // so we assume, that they are never required.
                // TODO perhaps, an explicit `required` property of the lookup mapping could solve this issue.
                $isRequired = false;
                $toCol = $toSheet->getColumns()->addFromExpression($toExpr->getAttribute()->getRelationPath()->getRelationFirst()->getAlias());
                $subsheetTpl = [
                    'object_alias' => $toExpr->getAttribute()->getRelationPath()->getEndObject()->getAliasWithNamespace(),
                    'rows' => []
                ];
            } else {
                $isRequired = $toExpr->isMetaAttribute() ? $toExpr->getAttribute()->isRequired() : false;
                $toCol = $toSheet->getColumns()->addFromExpression($toExpr);
            }
        } else {
            if ($this->needsSubsheet($toExpr)) {
                $isSubsheet = true;
            }
        }

        // Get the lookup data - either from cache or by reading it
        $readAll = $this->willReadAll();
        $matchesLookup = [];
        if ($this->lookupCache === null) {
            $matches = $this->getMatches();
            $lookupSheet = DataSheetFactory::createFromObject($this->getLookupObject());
            $lookupCol = $lookupSheet->getColumns()->addFromExpression($lookupExpr);
            // Add lookup columns for every match
            foreach ($matches as $i => $match) {
                $matchLookupCol = $lookupSheet->getColumns()->addFromExpression($match['lookup']);
                $matchesLookup[$i] = [
                    'lookupCol' => $matchLookupCol,
                    'lookupColName' => $matchLookupCol->getName(),
                ];
            }
        } else {
            $lookupSheet = $this->lookupCache['lookupSheet'];
            $lookupCol = $this->lookupCache['lookupCol'];
            $matches = $this->lookupCache['matches'];
            $matchesLookup = $this->lookupCache['matchesLookup'];
        }

        // Prepare from-data keys to compare with lookup data
        $matchesFrom = [];
        $fromColsHaveValues = false;
        foreach ($matches as $i => $match) {
            $fromExpr = ExpressionFactory::createForObject($fromSheet->getMetaObject(), $match['from']);
            switch (true) {
                // If it's a column in the from-data, use its values for filtering
                case $fromCol = $fromSheet->getColumns()->getByExpression($fromExpr):
                    $matchesFrom[$i] = [
                        'fromCol' => $fromCol,
                        'fromColName' => $fromCol->getName()
                    ];
                    /* TODO pre-parse all from values to speed up comparison later on
                    $fromType = $fromCol->getDataType();
                    $fromVals = [];
                    foreach ($fromCol->getValues() as $fromVal) {
                        $fromVals[] = $fromType->parse($fromVal);
                    }
                    $matchesFrom[$i]['fromValsParsed'] = $fromVals;
                    */

                    // If there are no from values
                    if ($fromCol->isEmpty(true) === false) {
                        $fromColsHaveValues = true;
                    }
                    // Add a filter to the lookup-sheet if not told to read it all anyhow
                    if ($readAll === false) {
                        $lookupSheet->getFilters()->addConditionFromValueArray($match['lookup'], $fromCol->getValues());
                    }
                    break;
                // If it is a constant or a static formula - use its value
                // IDEA maybe we can even evaluate non-static formulas with the help of the from-data???
                // On the other hand, we are adding these expressions to the from-data automatically. Why bother here?
                case $fromExpr->isStatic():
                    $fromColsHaveValues = true;
                    $staticVal = $fromExpr->evaluate();
                    $matchesFrom[$i]['static'] = $staticVal;
                    $lookupSheet->getFilters()->addConditionFromString($match['lookup'], $staticVal, ComparatorDataType::EQUALS);
                    break;
                default:
                    // If not enough data, but explicitly configured to ignore it, exit here
                    if ($this->getIgnoreIfMissingFromColumn() === true && ($fromExpr->isMetaAttribute() || $fromExpr->isFormula() || $fromExpr->isUnknownType())) {
                        $logbook?->addLine($log . ' Ignored because `ignore_if_missing_from_column` is `true` and not from-data was found.');
                        return $toSheet;
                    }
                    throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Missing column "' . $match['from'] . '" in from-data for a lookup mapping!');
            }
        }
        
        // If none of the from-columns have values, we do not need to read any data - it will be empty anyway!
        // In this case, ALL from-rows are definitely unmatched, so we can directly check, if we need an error
        // and exit here. This will also prevent worst-cases like reading all available lookup data just because
        // the filters do not have values.
        if ($fromColsHaveValues === false) {
            $toColVals = [];
            if ($isRequired === true) {
                $this->handleUnmatchedRows($fromSheet->getRows(), $toColVals, $fromSheet, $lookupSheet, $toSheet, $toCol);
            }
            $logbook?->addLine($log);
            return $toSheet;
        }
        
        // Read the lookup data if not using cache or cache not filled yet
        if ($readAll === false || $this->lookupCache === null) {
            $lookupSheet->dataRead();
        }
        
        // Fill the cache if required
        if ($readAll === true && $this->lookupCache === null) {
            $this->lookupCache = [
                'lookupSheet' => $lookupSheet,
                'lookupCol' => $lookupCol,
                'matches' => $matches,
                'matchesLookup' => $matchesLookup,
                'matchesFrom' => $matchesLookup
            ];
        }

        $lookupColName = $lookupCol->getName();
        $toColVals = [];
        if ($toExpr->isMetaAttribute()) {
            $toValDelim = $toExpr->getAttribute()->getValueListDelimiter();
        } else {
            $toValDelim = EXF_LIST_SEPARATOR;
        }

        // For every row in the from-sheet we will create a row in the to-sheet
        $unmatchedRows = [];
        foreach ($fromSheet->getRows() as $iFromRow => $fromRow) {
            $toColVals[$iFromRow] = null;
            // Collect all from-values into a single string to quickly find out
            $fromRowValsJoined = '';
            foreach ($matches as $match) {
                $fromRowValsJoined .= trim($fromRow[$match['from']] ?? '');
            }
            // Look for matching lookup rows for this from-row
            foreach ($lookupSheet->getRows() as $lookupRow) {
                $prevVal = $toColVals[$iFromRow];
                // If any of the keys DO NOT match, continue with next lookup row
                foreach ($matches as $iMatch => $match) {
                    // Convert both values to the data type of the lookup side so
                    // that both are in the same format
                    $matchType = $matchesLookup[$iMatch]['lookupCol']->getDataType();
                    $matchVal = $lookupRow[$match['lookup']];
                    if (is_array($matchesFrom[$iMatch]) && array_key_exists('static', $matchesFrom[$iMatch])) {
                        $fromVal = $matchesFrom['static'];
                    } else {
                        $fromVal = $fromRow[$matchesFrom[$iMatch]['fromColName'] ?? $match['from']];
                    }
                    // TODO move parsing from-data up to where from data is checked
                    // to avoid parsing the from-value over and over for every lookup
                    // row and for every match
                    try {
                        $fromVal = $matchType->parse($fromVal);
                    } catch (DataTypeExceptionInterface $e) {
                        continue 2;
                    }
                    // Compare WITHOUT strict type checking here! This ensures, that "1" is equal to 1
                    // and "1.0" is equal to 1.0
                    if (trim($matchVal ?? '') != trim($fromVal ?? '')) {
                        continue 2;
                    }
                }
                // Now we have the value we were looking for
                $lookupVal = $lookupRow[$lookupColName];
                // If it belongs into a subsheet, make sure the value is saved as an array representation
                // of a data sheet. Otherwise, save it as-is.
                if ($isSubsheet) {
                    if (! is_array($prevVal)) {
                        // Copy the template. Remember, that assigning arrays in PHP will do copy-on-wirte
                        $toColVals[$iFromRow] = $subsheetTpl;
                    }
                    if ($lookupVal !== null && $lookupVal !== '') {
                        foreach (explode($toValDelim, $lookupVal) as $val) {
                            $toColVals[$iFromRow]['rows'][] = [$toExpr->getAttribute()->getAlias() => $val];
                        }
                    }
                } else {
                    if ($prevVal === null) {
                        $toColVals[$iFromRow] = $lookupVal;
                    } else {
                        throw new DataMappingFailedError($this, $fromSheet, $toSheet, 'Lookup for "' . $toExpr->__toString() . '" returned more than 1 value on row ' . $iFromRow);
                    }
                }
            }
            
            // If row could not be matched to any lookup row, we might have to respond.
            if(null === ($toColVals[$iFromRow] ?? null)) {
                // If we do not have a lookup-value, that is perfectly fine if we did not have a
                // from-value either.
                if (! $isRequired && '' === trim($fromRowValsJoined)) {
                    // In case of subsheets, leaving the cell empty will actually not change anything. To really
                    // set it to an empty value, we need an empty subsheet here.
                    if ($isSubsheet) {
                        $toColVals[$iFromRow] = $subsheetTpl;
                    }
                }
                // Otherwise this from-row is a miss, and we need to handle it according to `if_not_found`
                else {
                    // Cache unmatched row.
                    $unmatchedRows[$iFromRow] = $fromRow;
                    // Some configurations require, that we stop processing after encountering our first unmatched row.
                    if ($this->stopOnFirstMiss) {
                        break;
                    }
                }
            }
        }

        $this->handleUnmatchedRows($unmatchedRows, $toColVals, $fromSheet, $lookupSheet, $toSheet, $toCol);
        $toCol->setValues($toColVals);

        $logbook?->addLine($log);
        
        return $toSheet;
    }

    /**
     * Handles any unmatched rows, according to the configuration of this mapper.
     *
     * @param array               $unmatchedRows
     * @param array               $toColumnValues
     * @param DataSheetInterface  $fromSheet
     * @param DataSheetInterface  $lookupSheet
     * @param DataSheetInterface  $toSheet
     * @param DataColumnInterface $toCol
     * @return void
     */
    protected function handleUnmatchedRows(
        array $unmatchedRows, 
        array &$toColumnValues,
        DataSheetInterface $fromSheet,
        DataSheetInterface $lookupSheet,
        DataSheetInterface $toSheet,
        DataColumnInterface $toCol
    ) : void
    {
        if(empty($unmatchedRows)) {
            return;
        }
        
        $error = null;
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        switch ($this->notFoundBehavior) {
            // Throw an error.
            case self::IF_NOT_FOUND_ERROR_FIRST:
            case self::IF_NOT_FOUND_ERROR_ALL:
                $rowNrs = array_keys($unmatchedRows);
                $matches = $this->getMatches();
                $matchKeys = [];
                foreach ($unmatchedRows as $fromRow) {
                    $rowKeys = [];
                    foreach ($matches as $match) {
                        $rowKeys[] = $fromRow[$match['from']];
                    }
                    if (count($rowKeys) > 1) {
                        $matchKeys[] = '["' . implode('", "', $rowKeys) . '"]';
                    } else {
                        $matchKeys[] = '"' . $rowKeys[0] . '"';
                    }
                }
                $errorUxon = $this->getIfNotFoundError();
                $message = $translator->translate(
                    'DATASHEET.ERROR.LOOKUP_FAILED', 
                    [
                        '%values%' => implode(', ', $matchKeys),
                        '%lookup_object%' => $lookupSheet->getMetaObject()->getName()
                    ],
                    count($unmatchedRows)
                );
                $error = new DataSheetMissingRequiredValueError(
                    $fromSheet, 
                    $message, 
                    $errorUxon->getProperty('code') ?? '80YWY1Z', 
                    null, 
                    $toCol, 
                    $rowNrs
                );
                $error->setUseExceptionMessageAsTitle(true);
            break;
            // Set a fixed value.
            case self::IF_NOT_FOUND_FALLBACK:
                try {
                    $fallbackValue = $this->fallbackValue;
                    $parsePerRow = false;
                    $expression = null;
                    // If value is a formula, evaluate it.
                    if (Expression::detectFormula($fallbackValue) === true) {
                        $expression = ExpressionFactory::createFromString($this->getWorkbench(), $fallbackValue);
                        if ($expression->isStatic()) {
                            $fallbackValue = $expression->evaluate() ?? '';
                        } else {
                            $fallbackValue = null;
                            $parsePerRow = true;
                        }
                    } else {
                        $fallbackValue = $toCol->getDataType()->parse($fallbackValue);
                    }

                } catch (\Throwable $e) {
                    $rowNrs = array_keys($unmatchedRows);
                    $error = new DataSheetMissingRequiredValueError(
                        $fromSheet,
                        null,
                        null,
                        $e,
                        $toCol,
                        $rowNrs
                    );
                }
                
                foreach ($unmatchedRows as $rowNr => $row) {
                    // Dynamic formulas must be parsed per row.
                    if($parsePerRow) {
                        $fallbackValue = $expression?->evaluate($fromSheet, $rowNr);

                        try {
                            $fallbackValue = $toCol->getDataType()->parse($fallbackValue);
                        } catch (\Throwable $e)
                        {
                            $rowNrs = array_keys($unmatchedRows);
                            $error = new DataSheetMissingRequiredValueError(
                                $fromSheet,
                                null,
                                null,
                                $e,
                                $toCol,
                                $rowNrs
                            );
                        }
                    }
                    
                    $toColumnValues[$rowNr] = $fallbackValue;
                }
            break;
        }
        
        if($error !== null) {
            if($this->notFoundErrorUxon) {
                $error->getMessageModel($this->getWorkbench())->importUxonObject($this->notFoundErrorUxon);
            }
            
            throw $error;
        }
    }

    /**
     * Checks if the expression is a meta attribute and if it is a reverse relation
     * @param \exface\Core\Interfaces\Model\ExpressionInterface $expr
     * @return bool
     */
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
        $this->clearCache();
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
     * Pairs of attribtues to match when searching lookup data: attribute from the from-sheet + attribute of the lookup
     * object
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
        $this->clearCache();
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
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\DataSheets\Mappings\AbstractDataSheetMapping::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return DataSheetLookupMappingSchema::class;
    }

    /**
     * 
     * @return bool
     */
    protected function getIgnoreIfMissingFromColumn() : bool
    {
        return $this->ignoreIfMissingFromColumn;
    }

    /**
     * Set to TRUE if this mapping is only to be applied if there is a corresponding from-data
     * 
     * By default the mapping will result in an error if the from-data does not have the 
     * required data.
     * 
     * @uxon-property ignore_if_missing_from_column
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return DataColumnMapping
     */
    protected function setIgnoreIfMissingFromColumn(bool $trueOrFalse) : DataMappingInterface
    {
        $this->ignoreIfMissingFromColumn = $trueOrFalse;
        return $this;
    }

    /**
     * Customize the error that will be shown if the lookup does not find any values
     * 
     * **NOTE:** this requires `if_not_found` to be set to `error_on_first` or `error_cumulative`! 
     * 
     * You can modify the following properties of the error:
     * 
     * - `title` - the text, that the user will see. By default, that text is generated automatically including
     * the affected values and row number. If you modify it, you can only use static text though (no placeholders).
     * - `code` - use a custom error code. You can create a new message model in `Administration > Metamodel > Messages`
     * with a custom hint and description. Place the code of the create error here, and all settings from that message
     * model will be used except for the title - that is controlled separately
     * - `type` - change the type of the message shown in case of an error - e.g. make it a `warning` 
     * 
     * @uxon-property if_not_found_error
     * @uxon-type \exface\Core\CommonLogic\Model\Message
     * @uxon-template {"code":""}
     * 
     * @param UxonObject|null $uxon
     * @return $this
     */
    protected function setIfNotFoundError(?UxonObject $uxon) : LookupMapping
    {
        $this->notFoundErrorUxon = $uxon;
        return $this;
    }

    /**
     * @return UxonObject|null
     */
    protected function getIfNotFoundError() : UxonObject
    {
        return $this->notFoundErrorUxon ?? new UxonObject();
    }

    /**
     * What should happen, if one or more rows in the `from-sheet` cannot be matched to any
     * row in the `lookup-sheet` (default is `leave_empty`). 
     * 
     * You can choose from these options:
     * - `leave_empty`: Unmatched rows produce an empty output in the `to-sheet`. No error will be thrown.
     * - `use_fallback`: Unmatched rows will use the value defined in `if_not_found_fallback_value`. No error will be
     * thrown.
     * - `error_on_first`: The first unmatched row will throw an error, immediately terminating the process.
     * - `error_cumulative`: All unmatched rows will be combined into one error that will be thrown before any changes
     * are commited. This terminates the process, after all lookups have been completed.
     * 
     * @uxon-property if_not_found
     * @uxon-type [leave_empty,error_on_first,error_cumulative,if_not_found_fallback_value]
     * @uxon-template leave_empty
     * 
     * @param string $value
     * @return $this
     */
    protected function setIfNotFound(string $value) : LookupMapping
    {
        $this->notFoundBehavior = $value;
        $this->stopOnFirstMiss = in_array($value, self::STOP_ON_FIRST_MISS); 
        
        return $this;
    }

    /**
     * @return string
     */
    protected function getIfNotFound() : string
    {
        return $this->notFoundBehavior;
    }

    /**
     * @return bool
     */
    protected function getStopOnFirstMiss() : bool
    {
        return $this->stopOnFirstMiss;
    }

    /**
     * Define a fallback value for unmatched rows.
     * 
     * NOTE: This setting only matters if `if_not_found` is set to `use_fallback_value`.
     * 
     * @uxon-property if_not_found_fallback_value
     * @uxon-type metamodel:formula|string
     * 
     * @param mixed $value
     * @return $this
     */
    protected function setIfNotFoundFallbackValue(string $value) : LookupMapping
    {
        $this->fallbackValue = $value;
        return $this;
    }

    /**
     * @return string|null
     */
    protected function getIfNotFoundFallbackValue() : ?string
    {
        return $this->fallbackValue;
    }

    /**
     * @return void
     */
    protected function clearCache() : void
    {
        $this->lookupCache = null;
    }

    /**
     * @return bool
     */
    protected function willReadAll() : bool
    {
        return $this->readAll;
    }

    /**
     * Set to TRUE to read ALL possible lookup data and perform lookups in-memory instead of reading for every from-data sheet separately
     * 
     * This mapper searches for lookup values for the entire from-data. This means, there will be
     * one read operation per input DataSheet. This is fine for most actions, but may produce significant
     * overhead if there are a lot of separate mappings performed - e.g. when importing data row-by-row.
     * An alternative would be to `read_all` available data at once and do the lookups in-memory. This
     * will only conduct a single read operation, but will read all data of the lookup action.
     * 
     * @uxon-property read_all
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @param bool $trueOrFalse
     * @return $this
     */
    protected function setReadAll(bool $trueOrFalse) : LookupMapping
    {
        $this->readAll = $trueOrFalse;
        return $this;
    }
}