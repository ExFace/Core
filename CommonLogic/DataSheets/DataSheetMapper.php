<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToJsonMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\JsonToRowsMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\LookupMapping;
use exface\Core\CommonLogic\Debugger\LogBooks\MarkdownLogBook;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetMapperInterface;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Uxon\DataSheetMapperSchema;
use exface\Core\Interfaces\DataSheets\DataMappingInterface;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Factories\ExpressionFactory;
use exface\Core\Exceptions\DataSheets\DataMapperRuntimeError;
use exface\Core\Exceptions\DataSheets\DataMapperConfigurationError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\CommonLogic\DataSheets\Mappings\ActionToColumnMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToFilterMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataFilterToColumnMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataJoinMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataToSubsheetMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\DataUnpivotMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\RowFilterMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\SubsheetMapping;
use exface\Core\Interfaces\Exceptions\DataMappingExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataMappingFailedError;
use exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToVariableMapping;
use exface\Core\CommonLogic\DataSheets\Mappings\VariableToColumnMapping;
use exface\Core\CommonLogic\Debugger\LogBooks\DataLogBook;
use exface\Core\DataTypes\PhpClassDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\DataSheets\Mappings\DataCheckMapping;
use exface\Core\Interfaces\Exceptions\DataMapperExceptionInterface;

/**
 * Maps data from one data sheet to another using different types of mappings for columns, filters, etc.
 * 
 * The mapper performs multiple mapping operations consequently transfering (= mapping) 
 * data from the from-data-sheet to the to-data-sheet. 
 * 
 * ## Mappings types 
 * 
 * How exactly the data is mapped depends on the type of mapping being used: 
 * 
 * - `column_to_column_mappings` transfer values from columns of the from-sheet to columns
 * in the to-sheet. Their `from` expression can also be a calculation allowing to change
 * values within the mapping (e.g. `=(version + 1)` or even use static calculation like `=Now()`.
 * - `column_to_filter_mappings` create filters in the to-sheet from values of from-heet columns.
 * - `filter_to_column_mappings` fill to-sheet columns with values of from-sheet filters.
 * - `data_to_subsheet_mappings` allow to create subsheets in the to-sheet from values of the from-sheet
 * - `subsheet_mappings` apply mappers to subsheets contained in the cells of nested data columns
 * - `joins` can join arbitrary data in a way similar to SQL JOINs
 * - `unpivot_mappings` Transform selected columns of the from-sheet to rows in the to-sheet (resulting 
 * in two columns - labels and values)
 * - `action_to_column_mappings` will perform an action on the from-sheet and map data from
 * the actions result to the to-sheet
 * - `row_filter` allows to remove rows either from the to-sheet or even from the from-sheet in
 * order to exclude them from subsequent mappings
 * 
 * ## Order of execution
 * 
 * Mappings are applied in the order of definition: e.g. if you place `joins` first in the mappers 
 * UXON followed by `column_to_column_mappings`, the column mappings will be applied after the
 * data was joined, so you will be able to map newly joined columns.
 * 
 * If you need full control over the order of the mappings, use the generic `mappings` array
 * where you must define the class of each mapping however. All the specific arrays like
 * `column_to_column_mappings` are just there for convenience - technically they all just fill 
 * the `mappings`.
 * 
 * ## Variables
 * 
 * If you need certain values to "survive" a number of mappings an be reused afterwards, you can 
 * store the in variables using special variable mappings. This is mainly used in action chains,
 * where certain values need to be passed from one action to another. 
 * 
 * - `column_to_variable_mappings` to store values of the from-sheet
 * - `variable_to_column_mappings` to read stored values and put them back into a data sheet
 * 
 * These mappings use context variables, that remain available throughout the request.
 * 
 * **NOTE:** do not use `=GetContextVar()` formulas on variables set by a previous mapping of
 * the same mapper - the variables will not yet be available! Use `variable_to_column_mappings`
 * instead. Remember, that any mapper will attempt to read missing from-values (see below), 
 * which will include your formula, of course. Thus the formula will be evaluated __before__ 
 * the variables are populated.
 * 
 * ## Inheriting properties of the from-sheet
 * 
 * To decrease the number of explicit mappings, the mapper can make the to-sheet inherit columns, 
 * filter and other things from the to-sheet:
 * 
 * - `inherit_columns`
 * - `inherit_filters`
 * - `inherit_sorters`
 * 
 * ## Handling empty from-sheets
 * 
 * If the from-sheet is empty, all mappers are still applied and will often produce at least one new row
 * in the to-sheet: for example, `column_to_column_mappings` with static formulas will add a new row filling 
 * it with the calculated value.
 * 
 * Set `inherit_empty_data` to `true` to force the to-sheet to be empty if the from-sheet was.
 * 
 * ## Reading missing from-values
 * 
 * In most cases, you can define any readable values in `from` properties of mappers and they will
 * be read from the data source automatically. This is very convenient, but is sometimes also a source
 * of confusion. This is why there are certain situation, when this does not work automatically or can
 * be disabled explicitly:
 * 
 * - `read_missing_from_data` - can be set to `true` or `false` to control reading missing values explicitly
 * - If `read_missing_from_data` is not set, missing values will be read automatically if
 *      - the from-sheet is empty
 *      - the from-sheet has a non-empty UID column AND is fresh (= its data was not altered). This makes
 *      sure, that additional data is only loaded if we know exactly where to take it from and we can assume,
 *      that it is still consistent with the current state of the data sheet. 
 *      
 * ## Handling duplicate rows
 * 
 * When using `column_to_column_mappings` to map relation keys of one object to UIDs of another on multiple 
 * rows, you can easily end up with duplicate UIDs in the to-sheet. By default, duplicate rows are automatically 
 * removed if the to-sheet has a non-empty UID-column. You can, however, control this explicitly using the 
 * `remove_duplicate_rows` option. 
 * 
 * @see DataSheetMapperInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSheetMapper implements DataSheetMapperInterface 
{
    use ImportUxonObjectTrait;
    
    const INHERIT_ALL = 'all';
    
    const INHERIT_NONE = 'none';
    
    const INHERIT_MATCHING_ATTRIBUTES = 'matching_attributes';
    
    const INHERIT_COLUMNS_OWN_ATTRIBUTES = 'own_attributes';
    
    const INHERIT_COLUMNS_OWN_SYSTEM_ATTRIBUTES = 'own_system_attributes';
    
    const INHERIT_COLUMNS_ALL_SYSTEM_ATTRIBUTES = 'all_system_attributes';
    
    private $workbench = null;
    
    private $fromMetaObject = null;
    
    private $toMetaObject = null;
    
    private $mappings = [];
    
    private $inheritColumns = null;
    
    private $inheritFilters = null;
    
    private $inheritSorters = null;
    
    private $inheritEmptyData = false;
    
    private $refreshDataAfterMapping = false;
    
    private $readMissingData = true;
    
    private $removeDuplicateRows = null;
    
    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::map()
     */
    public function map(DataSheetInterface $fromSheet, bool $readMissingColumns = null, LogBookInterface $logbook = null) : DataSheetInterface
    {
        if (! $fromSheet->getMetaObject()->is($this->getFromMetaObject())){
            throw new DataMapperRuntimeError($this, $fromSheet, 'Input data sheet based on "' . $fromSheet->getMetaObject()->getAliasWithNamespace() . '" does not match the input object of the mapper "' . $this->getFromMetaObject()->getAliasWithNamespace() . '"!', null, null, $logbook);
        }
        
        $fromSheetWasEmpty = $fromSheet->isEmpty();
        $freshStamp = $fromSheet->getFreshStamp();
        
        $logbook = $logbook ?? new MarkdownLogBook('Data mapper');
        $logbook->addLine('Mapping ' . $fromSheet->countRows() . ' rows of **' . $fromSheet->getMetaObject()->__toString() . '** to **' . $this->getToMetaObject()->__toString() . '**');
        $logbook->addIndent(1);
            
        $diagram = 'flowchart LR';
        $diagram .= "\n\tFromSheet(From-sheet) -->|" . DataLogBook::buildMermaidTitleForData($fromSheet) . "|";
        
        // Make sure, the from-sheet has everything needed
        try {
            $fromSheet = $this->prepareFromSheet($fromSheet, $readMissingColumns, $logbook);
        } catch (\Throwable $e) {
            $logbook->addLine('**ERROR:** ' . $e->getMessage());
            if (! ($e instanceof DataMapperExceptionInterface)) {
                $e = new DataMapperRuntimeError($this, $fromSheet, 'Failed to read missing data for data mapper', null, $e, $logbook);
            }
            throw $e;
        }
        
        if ($freshStamp !== $fromSheet->getFreshStamp()) {
            $diagram .= " RefreshFromSheet[Read missing data]";
            $diagram .= "\n\tRefreshFromSheet -->|" . DataLogBook::buildMermaidTitleForData($fromSheet) . "|";
        }
        
        // Create an empty to-sheet
        $toSheet = DataSheetFactory::createFromObject($this->getToMetaObject());
        
        // Inherit stuff
        $logbook->addLine('Inheriting: ');
        $logbook->addIndent(1);

        // Inherit columns if neccessary
        if (self::INHERIT_NONE !== $inheritMode = $this->getInheritColumns()){
            $processedNames = [];
            foreach ($fromSheet->getColumns() as $fromCol){
                if ($inheritMode === self::INHERIT_COLUMNS_ALL_SYSTEM_ATTRIBUTES && (! $fromCol->isAttribute() || ! $fromCol->getAttribute()->isSystem())) {
                    continue;
                }
                if ($inheritMode === self::INHERIT_COLUMNS_OWN_SYSTEM_ATTRIBUTES && (! $fromCol->isAttribute() || ! $fromCol->getAttribute()->isSystem() || $fromCol->getAttribute()->isRelated())) {
                    continue;
                }
                if ($inheritMode === self::INHERIT_COLUMNS_OWN_ATTRIBUTES && (! $fromCol->isAttribute() || $fromCol->getAttribute()->isRelated())) {
                    continue;
                }
                if ($inheritMode === self::INHERIT_MATCHING_ATTRIBUTES && (! $fromCol->isAttribute() || ! $toSheet->getMetaObject()->hasAttribute($fromCol->getAttributeAlias()))) {
                    continue;
                }
                $processedNames[] = "`{$fromCol->getName()}`";
                $toSheet->getColumns()->add(DataColumnFactory::createFromUxon($toSheet, $fromCol->exportUxonObject()));
            }
            $logbook->addLine(count($processedNames) . " columns (mode `{$inheritMode}`): " . implode(', ', $processedNames));
            try {
                $toSheet->importRows($fromSheet);
            } catch (\Throwable $e) {
                $logbook->addLine('**ERROR**: ' . $e->getMessage());
                throw new DataMapperRuntimeError($this, $fromSheet, 'Cannot inherit columns in data mapper. ' . $e->getMessage(), null, null, $logbook);
            }
        } else {
            $logbook->addLine('0 columns');
        }
        
        // Inherit filters if neccessary
        if (self::INHERIT_NONE !== $inheritMode = $this->getInheritFilters()){
            $logbook->addLine("Filters (mode `{$inheritMode}`): `{$fromSheet->getFilters()->__toString()}`");
            try {
                if ($inheritMode === self::INHERIT_MATCHING_ATTRIBUTES) {
                    $toSheet->setFilters($fromSheet->getFilters()->rebaseWithMatchingAttributesOnly($toSheet->getMetaObject()));
                } else {
                    $toSheet->setFilters($fromSheet->getFilters());
                }
            } catch (\Throwable $e) {
                $logbook->addLine('**ERROR**: ' . $e->getMessage());
                throw new DataMapperRuntimeError($this, $fromSheet, 'Cannot inherit filters in data mapper. ' . $e->getMessage(), null, null, $logbook);
            }
        } else {
            $logbook->addLine('0 filters');
        }
        
        // Inherit sorters if neccessary
        if (self::INHERIT_NONE !== $inheritMode = $this->getInheritSorters()){
            try {
                $processedNames = [];
                foreach ($fromSheet->getSorters()->getAll() as $sorter) {
                    $toSheet->getSorters()->add($sorter);
                    $processedNames[] = "`{$sorter->__toString()}`";
                }
                $logbook->addLine(count($processedNames) . " sorters (mode `{$inheritMode}`): " . implode(', ', $processedNames));
            } catch (\Throwable $e) {
                $logbook->addLine('**ERROR**: ' . $e->getMessage());
                throw new DataMapperRuntimeError($this, $fromSheet, 'Cannot sorters in data mapper. ' . $e->getMessage(), null, null, $logbook);
            }
        } else {
            $logbook->addLine('0 sorters');
        }
        
        // Apply mappers
        $logbook->addLine('Applying mappers:', -1);
        $lastClass = null;
        $lastMapCnt = 1;
        foreach ($this->getMappings() as $i => $map) {
            try {
                
                $toSheet = $map->map($fromSheet, $toSheet, $logbook);
                $mapClass = get_class($map);
                if ($lastClass === $mapClass) {
                    $lastMapCnt++;
                } else {
                    $diagram .= " Map{$i}[\"{$lastMapCnt}x {$this->getMappingType($map)}\"]";
                    $diagram .= "\n\t Map{$i} -->|" . DataLogBook::buildMermaidTitleForData($toSheet) . "|";
                    $lastClass = $mapClass;
                    $lastMapCnt = 1;
                }
            } catch (\Throwable $e) {
                $logbook->addLine('**ERROR:** ' . $e->getMessage());
                $diagram .= " MapperError(Error)";
                $logbook->addCodeBlock($diagram, 'mermaid');

                if ($e instanceof DataMappingExceptionInterface) {
                    throw $e;
                }
                throw new DataMappingFailedError($map, $fromSheet, $toSheet, $e->getMessage(), null, $e);
            }
        }
        $diagram .= " ToSheet(To-sheet)";
        $logbook->addIndent(-1);
        
        // Make sure the to-sheet is empty if the from-sheet was empty and the empty state is to be inherited
        if ($this->getInheritEmptyData() && $fromSheetWasEmpty) {
            $toSheet->removeRows();
            $logbook->addLine('Emptied to-data because `inherit_empty_data` is `true`');
        }
        
        // Refresh data if needed
        if ($this->getRefreshDataAfterMapping()) {
            $toSheet->dataRead();
            $logbook->addLine('Refreshed to-data: read ' . $toSheet->countRows() . ' rows');
        }
        
        // Remove duplicate rows if explicitly required or the sheet has a UID column. If there is a UID column,
        // we can be sure, that equal rows actually are the same data item.
        if ($this->getRemoveDuplicateRows() === true || ($toSheet->hasUidColumn(true) && $toSheet->countRows() > 1 && $this->getRemoveDuplicateRows() !== false)) {
            $duplicateRows = $toSheet->removeRowDuplicates();
            $logbook->addLine('Removed ' . count($duplicateRows) . ' duplicate rows rows');
        }
        
        $logbook->addIndent(-1);
        $logbook->addCodeBlock($diagram, 'mermaid');
        $logbook->addLine("Mapper output: {$toSheet->countRows()} rows of {$toSheet->getMetaObject()->__toString()}");
        
        return $toSheet;
    }
    
    /**
     * Checks if all required columns are in the from-sheet and tries to add missing ones and reload the data.
     *  
     * @param DataSheetInterface $data_sheet
     * @param bool $readMissingColumns
     * @param LogBookInterface $logbook
     * @throws DataMapperRuntimeError
     * @return DataSheetInterface
     */
    protected function prepareFromSheet(DataSheetInterface $data_sheet, bool $readMissingColumns = null, LogBookInterface $logbook = null) : DataSheetInterface
    {
        // If we must not read any data, simply skip this method
        if ($readMissingColumns === false) {
            if ($logbook !== null) $logbook->addLine('Reading missing columns explicitly `false`');
            return $data_sheet;
        }
        
        if ($logbook !== null) $logbook->addLine('Checking input data for missing columns');

        // TODO for a reason not known anymore, there is $readMissingColumns and $this->getReadMissingFromData()
        // separately. It seems $readMissingColumns is passed from outside, and the other one is part of
        // the mapper configuration. But they do different things somehow. Can they be coupled?

        // If the sheet is empty, just fill it with the required columns and read everything 
        // (no UID values to filter in this case)
        if ($data_sheet->isEmpty() && $this->getReadMissingFromData() !== false) {
            foreach ($this->getMappings() as $map){
                foreach ($map->getRequiredExpressions($data_sheet) as $expr) {
                    $data_sheet->getColumns()->addFromExpression($expr);
                }
            }
            if ($logbook !== null) $logbook->addLine('Reading all columns for empty data sheet', 1);
            $data_sheet->dataRead();
            return $data_sheet;
        }
        
        $refreshed = false;
        
        // TODO #DataCollector needs to be used here instead of all the following logic
        
        // Now we know, reading missing data is not forbidden, but the sheet already has some data
        // Add missing columns automatically here if:
        // - the sheet has a UID column AND is fresh (no values changed) - this means it is more or 
        // less safe to read extra data from the data source.
        // - the missing data is related to the sheets data AND the required relation keys are present 
        // in the current data AND the relation path to the missing data only consists of forward (n-to-1)
        // relations: E.g. we can load `OBJECT__NAME` for attribute data having `OBJECT` columns even if
        // we do not have UIDs at hand, because we know for sure, which OBJECTs are referenced.
        if ($data_sheet->hasUidColumn(true) && $data_sheet->isFresh()) {
            $additionSheet = null;
            // See if any required columns are missing in the original data sheet. If so, add empty
            // columns and also create a separate sheet for reading missing data.
            $addedCols = [];
            $addedExprs = [];
            $effectedFormulas = [];
            foreach ($this->getMappings() as $map){
                foreach ($map->getRequiredExpressions($data_sheet) as $expr) {
                    // Skip the column if it already exists in the from-sheet
                    if ($data_sheet->getColumns()->getByExpression($expr)){
                        continue;
                    }
                    // Otherwise we need a separate data sheet to read the required data.
                    // Can't use the from-sheet itself as reading might overwrite other 
                    // values set by the user!
                    // So create an extra copy of the sheet and remove any columns except
                    // for the UID, that (as we know from above) hase meaningful values.
                    if ($additionSheet === null) {
                        $additionSheet = $data_sheet->copy();
                        foreach ($additionSheet->getColumns() as $col) {
                            if ($col !== $additionSheet->getUidColumn()) {
                                $additionSheet->getColumns()->remove($col);
                            }
                        }
                    }
                    $addedExprs[] = $expr->__toString();
                    // If the new expression is a formula, remember it to update its calculation
                    // after the mapping
                    if ($expr->isFormula()) {
                        $effectedFormulas[] = $expr;
                    }
                    // Now add readable stuff required for the expression to the addition sheet.
                    // But only if it does not exist yet in the from sheet as this would overwrite
                    // values set by the user!
                    // DO NOT add the expression itself as it might be a formula, that requires
                    // other columns from the from-sheet, that may not be available in the sheet
                    // with additional values. Formula will be recalculated later.
                    foreach ($expr->getRequiredAttributes() as $exprReqStr) {
                        $exprReq = ExpressionFactory::createForObject($data_sheet->getMetaObject(), $exprReqStr);
                        if ($data_sheet->getColumns()->getByExpression($exprReq)){
                            continue;
                        }
                        if ($exprReq->isMetaAttribute() && $exprReq->getAttribute()->isReadable()) {
                            $addedCols[] = $additionSheet->getColumns()->addFromExpression($exprReq);
                        }
                    }
                    // Add the expression to the from-sheet. This will mark it as not fresh, which
                    // is important below.
                    // TODO it does not feel right to change the from-sheet in a mapper. Maybe the
                    // columns shoud be removed at some later point of time?
                    $data_sheet->getColumns()->addFromExpression($expr);
                }
            }
            
            if ($logbook !== null) {
                if (! empty($addedCols)) {
                    $logbook->addLine('Found ' . count($addedCols) . ' columns to read for the mapper: `' . implode('`, `', $addedExprs) . '`', 1);
                } else {
                    $logbook->addLine('All columns required for mapping found in from-data', 1);
                }
            }
            
            // If columns were added to the original sheet, that need data to be loaded,
            // use the additional data sheet to load the data. This makes sure, the values
            // in the original sheet (= the input values) are not overwrittten by the read
            // operation.
            if (! $data_sheet->isFresh() && $this->getReadMissingFromData() === true){
                // Don't read anything if the object is not readable at all. 
                if ($data_sheet->getMetaObject()->isReadable() === false) {
                    $logbook->addLine('**WARNING:** it seems, the from-data as not fresh and needs to be read, but the object ' . $data_sheet->getMetaObject()->__toString() . ' is explicitly marked as not readable. The data will not be refreshed!');
                    return $data_sheet;
                }
                
                $additionSheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
                $additionSheet->dataRead();
                
                if ($logbook !== null) $logbook->addLine('Read ' . $additionSheet->countRows() . ' rows filtered by ' . $data_sheet->getUidColumn()->getName(), 1);
                
                $uidCol = $data_sheet->getUidColumn();
                $uidColName = $uidCol->getName();
                foreach ($additionSheet->getColumns() as $addedCol) {
                    foreach ($additionSheet->getRows() as $row) {
                        $uid = $row[$uidColName];
                        $rowNo = $uidCol->findRowByValue($uid);
                        if ($uid === null || $rowNo === false) {
                            throw new DataMapperRuntimeError($this, $data_sheet, 'Cannot load additional data in preparation for mapping! Trying to read ' . $addedCol->getName(), null, null, $logbook);
                        }
                        // Only set cell values if the column is an added column
                        // or the column does not exist yet in the original data sheet.
                        // It is important to check both because formula might lead to more columns being added.
                        if (in_array($addedCol, $addedCols, true) || $data_sheet->getColumns()->getByExpression($addedCol->getExpressionObj()) === FALSE) {
                            $data_sheet->setCellValue($addedCol->getName(), $rowNo, $row[$addedCol->getName()]);
                            $refreshed = true;
                        }
                    }
                }
                // Recalculate all formulas, that rely on the newly added columns
                foreach ($effectedFormulas as $expr) {
                    $data_sheet->getColumns()->getByExpression($expr)->setValuesByExpression($expr);
                }
            }
        } else { 
            // The original from-data has no UIDs or was not fresh right from the beginning
            // See if any attributes required for the missing columns are related in the way described above
            // the if(). If so, load the data separately and put it into the from-sheet. This is mainly usefull
            // for formulas.
            $fromObj = $this->getFromMetaObject();
            foreach ($this->getMappings() as $map) {
                foreach ($map->getRequiredExpressions($data_sheet) as $expr) {
                    if ($data_sheet->getColumns()->getByExpression($expr)) {
                        continue;
                    }
                    foreach ($expr->getRequiredAttributes() as $reqAlias) {
                        // Only process requried attribute aliases, that are not present as columns yet and
                        // have a non-empty relation path consisting only of forward relations
                        if ($data_sheet->getColumns()->getByExpression($reqAlias)) {
                            continue;
                        }
                        $reqAttr = $fromObj->getAttribute($reqAlias);
                        $reqRelPath = $reqAttr->getRelationPath();
                        if ($reqRelPath->isEmpty()) {
                            continue;
                        }
                        // Find the last relation in the path, where there is a key column with values
                        // in the current data.
                        $reqRelKeyCol = null;
                        $reqRelKeyColPath = null;
                        $reqRelColPath = RelationPathFactory::createForObject($fromObj);
                        $reqRelForwardOnly = true;
                        foreach ($reqRelPath->getRelations() as $reqRel) {
                            if ($reqRel->isForwardRelation()) {
                                $reqRelColPath = $reqRelColPath->appendRelation($reqRel);
                                if (($keyCol = $data_sheet->getColumns()->getByExpression($reqRelColPath->toString())) && $keyCol->isEmpty(true) === false) {
                                    $reqRelKeyCol = $keyCol;
                                    $reqRelKeyColPath = $reqRelColPath;
                                }
                            } else {
                                // If there are backwards-relations in the path, jus skip the whole thing,
                                // maybe some other parts of the code will deal with it.
                                $reqRelForwardOnly = false;
                                break;
                            }
                        }
                        // If we have found a target, read data for it
                        // IDEA collect all missing data based on the same object and read it at once instead of
                        // reading data for each missing column separately.
                        if ($reqRelForwardOnly === true && $reqRelKeyCol !== null) {
                            $targetCol = $data_sheet->getColumns()->addFromExpression($reqAlias);
                            $reqRelSheet = DataSheetFactory::createFromObject($reqRelKeyColPath->getEndObject());
                            $valCol = $reqRelSheet->getColumns()->addFromExpression(ExpressionFactory::createForObject($fromObj, $reqAlias)->rebase($reqRelKeyColPath->toString()));
                            $keyCol = $reqRelSheet->getColumns()->addFromAttribute($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute());
                            $reqRelSheet->getFilters()->addConditionFromValueArray($reqRelKeyColPath->getRelationLast()->getRightKeyAttribute()->getAliasWithRelationPath(), $reqRelKeyCol->getValues(), ComparatorDataType::IN);
                            $reqRelSheet->dataRead();
                            foreach ($reqRelKeyCol->getValues() as $fromRowIdx => $key) {
                                $targetCol->setValue($fromRowIdx, $valCol->getValue($keyCol->findRowByValue($key)));
                                $refreshed = true;
                            }
                            
                            if ($logbook !== null) $logbook->addLine('Read ' . $reqRelSheet->countRows() . ' rows for columns related to mapped data (object "' . $reqRelSheet->getMetaObject()->getAliasWithNamespace() . '")', 1);
                        }
                        
                    } // END foreach ($expr->getRequiredAttributes())
                } // END foreach($map->getRequiredExpressions($data_sheet))
            } // END foreach($this->getMappings())
        } // END if($data_sheet->hasUidColumn(true) && $data_sheet->isFresh())
          
        // Make sure the data is marked as fresh now to prevent further unneeded refreshes
        if ($refreshed === true) {
            $data_sheet->setFresh(true);
        }
        
        return $data_sheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getFromMetaObject()
     */
    public function getFromMetaObject() : MetaObjectInterface
    {
        if (is_null($this->fromMetaObject)){
            // TODO add error code
            throw new DataMapperConfigurationError($this, 'No from-object defined in data sheet mapper!');
        }
        
        return $this->fromMetaObject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromMetaObject()
     */
    public function setFromMetaObject(MetaObjectInterface $object) : DataSheetMapperInterface
    {
        $this->fromMetaObject = $object;
        return $this;
    }
    
    /**
     * The object to apply the mapping to (= the input of the mapping).
     * 
     * The mapping will only be applied to input data of this object or it's
     * derivatives!
     * 
     * @uxon-property from_object_alias
     * @uxon-type metamodel:object
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromObjectAlias()
     */
    public function setFromObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface
    {
        return $this->setFromMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getToMetaObject()
     */
    public function getToMetaObject() : MetaObjectInterface
    {
        if (is_null($this->toMetaObject)){
            // TODO add error code
            throw new DataMapperConfigurationError($this, 'No to-object defined in data sheet mapper!');
        }
        return $this->toMetaObject;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setToMetaObject()
     */
    public function setToMetaObject(MetaObjectInterface $toMetaObject) : DataSheetMapperInterface
    {
        $this->toMetaObject = $toMetaObject;
        return $this;
    }
    
    /**
     * The object of the resulting data sheet (after the mapping).
     *
     * Only set to `to_object_alias` explicitly if really neccessary. Leave empty for the
     * mapper owner (e.g. action) to set the target object automatically.
     *
     * @uxon-property to_object_alias
     * @uxon-type metamodel:object
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setFromObjectAlias()
     */
    public function setToObjectAlias(string $alias_with_namespace) : DataSheetMapperInterface
    {
        return $this->setToMetaObject($this->getWorkbench()->model()->getObject($alias_with_namespace));
    }
    
    /**
     * Map column expressions of the from-sheet to new columns of the to-sheet.
     * 
     * @uxon-property mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\AbstractDataSheetMapping[]
     * @uxon-template [{"class": "", "": ""}]
     * 
     * @param UxonObject $uxonArray
     * @throws DataMapperConfigurationError
     * @return DataSheetMapperInterface
     */
    protected function setMappings(UxonObject $uxonArray) : DataSheetMapperInterface
    {
        foreach ($uxonArray as $uxon) {
            $class = $uxon->getProperty('class');
            if (! $class || ! class_exists($class)) {
                throw new DataMapperConfigurationError($this, 'Invalid data mapper class "' . $class . '"!');
            }
            $mapping = new $class($this, $uxon);
            $this->addMapping($mapping);
        }
        return $this;
    }
    
    /**
     * Map column expressions of the from-sheet to new columns of the to-sheet.
     * 
     * @uxon-property column_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     * 
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * @deprecated Obsolete! Use setColumnToColumnMappings()
     * This method is only here for UXON backwards compatibility
     */
    protected function setColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        return $this->setColumnToColumnMappings($uxon);
    }
    
    /**
     * Creates filters from the values of a column
     * 
     * @uxon-property column_to_filter_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToFilterMapping[]
     * @uxon-template [{"from": "", "to": "", "comparator": "=="}]
     * 
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToFilterMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnToFilterMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Create columns from the values of filters
     *
     * @uxon-property filter_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataFilterToColumnMapping[]
     * @uxon-template [{"from": "", "from_comparator": "", "to": "", "to_single_row": false}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setFilterToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataFilterToColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Join other data similarly to left/right JOINs in SQL
     *
     * @uxon-property joins
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataJoinMapping[]
     * @uxon-template [{"join":"left","join_input_data_on_attribute":"","join_data_sheet_on_attribute":"","join_data_sheet":{"object_alias":"","columns":[{"attribute_alias":""}],"filters":[{"operator":"AND","conditions":[{"expression":"","comparator":"==","value":""}]}]}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setJoins(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataJoinMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Join other data similarly to left/right JOINs in SQL
     *
     * @uxon-property action_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\ActionToColumnMapping[]
     * @uxon-template [{"from": "", "to": "", "action": {"alias": ""}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setActionToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new ActionToColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Move values from column of the from-sheet to subsheets inside the to-sheet.
     * 
     * For example, if you have a sheet with UI pages, you can create a page group
     * with page mappings to all of these pages as a subsheet in its column `PAGE_GROUP_PAGES`.
     * If the result is saved, it would create new page group containing all pages from the
     * initial data sheet.
     * 
     * ```
     *  {
     *      "from_object_alias": "exface.Core.PAGE",
     *      "to_object_alias": "exface.Core.PAGE_GROUP",
     *      "column_to_column_mappings": [
     *          {"from": "='Unnamed page group'", "to": "NAME"}
     *      ],
     *      "to_subsheet_mappings": [
     *          {
     *              "subsheet_relation_path": "PAGE_GROUP_PAGES",
     *              "subsheet_mapper": {
     *                  "column_to_column_mappings": [
     *                      {"from": "UID", "to": "PAGE"}
     *                  ]
     *              }
     *          }
     *      ]
     *  }
     * 
     * ```
     *
     * @uxon-property data_to_subsheet_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataToSubsheetMapping[]
     * @uxon-template [{"subsheet_relation_path": "", "subsheet_mapper": {"column_to_column_mappings": [{"from": "", "to": ""}]}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setDataToSubsheetMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataToSubsheetMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Transform selected columns of the from-sheet to rows in the to-sheet (resulting in two columns - labels and values)
     * 
     * @uxon-property unpivot_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataUnpivotMapping[]
     * @uxon-template [{"from_columns": [""], "to_labels_column": "", "to_values_column": ""}]
     * 
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setUnpivotMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataUnpivotMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Apply a mapper to a column with subsheets - that is to each subsheet
     *
     * @uxon-property subsheet_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\SubsheetMapping[]
     * @uxon-template [{"from_subsheet_relation_path": "", "to_subsheet_relation_path": "", "subsheet_mapper": {"column_to_column_mappings": [{"from": "", "to": ""}]}}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setSubsheetMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new SubsheetMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Remove rows, that do not match a condition group
     *
     * @uxon-property row_filter
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\RowFilterMapping
     * @uxon-template {"mode": "keep_matches_only", "apply_to": "to-sheet", "filter": {"operator": "AND", "conditions": [{"expression": "","comparator": "==","value": ""}]}}
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setRowFilter(UxonObject $uxon) : DataSheetMapperInterface
    {
        $this->addMapping(new RowFilterMapping($this, $uxon));
        return $this;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getMappings()
    */
    public function getMappings() : array
    {
        return $this->mappings;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::addMapping()
     */
    public function addMapping(DataMappingInterface $mapping) : DataSheetMapperInterface
    {
        $this->mappings[] = $mapping;
        return $this;
    }
    
    /**
     * Returns one of the INHERIT_X constants
     * 
     * @return string
     */
    protected function getInheritColumns() : string
    {
        return $this->inheritColumns ?? ($this->canInheritColumns() ? self::INHERIT_ALL : self::INHERIT_NONE);
    }
    
    /**
     * Set to `none` to prevent the to-sheet from inheriting compatible columns from the from-sheet.
     * 
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all columns by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     * 
     * Available options:
     * 
     * - `all` - all columns will be inherited
     * - `none` - no columns will be inherited, even no system columns!
     * - `own_attributes` - only columns for direct attributes of the from-object will be inherited
     * - `own_system_attributes` - only system attributes of the from-object will be inherited. This
     * is very usefull if you just need the UID and everything else required for a potential update.
     * - `all_system_attributes` - only system attributes of the from-object and any related objects
     * present in the from-sheet. This is a good choice for data sheets, that update their main object 
     * along with certain attributes of related objects.
     * 
     * @uxon-property inherit_columns
     * @uxon-type [all,none,own_attributes,own_system_attributes,all_system_attributes,matching_attributes]
     * @uxon-template own_system_attributes
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritColumns()
     */
    public function setInheritColumns($value) : DataSheetMapperInterface
    {
        switch (true) {
            case is_string($value):
                $value = mb_strtolower($value);
                if (! defined('self::INHERIT_COLUMNS_' . mb_strtoupper($value)) && ! defined('self::INHERIT_' . mb_strtoupper($value))) {
                    throw new DataMapperConfigurationError($this, 'Invalid value "' . $value . '" for data mapper option `inherit_columns`');
                }
                break;
            case $value === false:
                $value = self::INHERIT_NONE;
                break;
            case $value === true:
                $value = self::INHERIT_ALL;
                break;
        }
        
        if ($value !== self::INHERIT_NONE && $value !== self::INHERIT_MATCHING_ATTRIBUTES){
            if (! $this->canInheritColumns()) {
                throw new DataMapperConfigurationError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit columns from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritColumns = $value;
        return $this;
    }
    
    /**
     * Set to TRUE to inherit only system columns
     * 
     * @deprecated use setInheritColumns(self::INHERIT_COLUMNS_SYSTEM_ATTRIBUTES) instead!
     */
    private function setInheritColumnsOnlyForSystemAttributes(bool $value) : DataSheetMapperInterface
    {
        if ($value) {
            if (! $this->canInheritColumns()) {
                throw new DataMapperConfigurationError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit columns from sheets of "' . $this->getFromMetaObject() . '"!');
            }
            $this->setInheritColumns(self::INHERIT_COLUMNS_ALL_SYSTEM_ATTRIBUTES);
        }
        return $this;
    }    
    
    /**
     * Returns one of the INHERIT_X constants
     *
     * @return boolean
     */
    protected function getInheritFilters() : string
    {
        return $this->inheritFilters ?? ($this->canInheritFilters() ? self::INHERIT_ALL : self::INHERIT_NONE);
    }
    
    /**
     * Set to `none` to prevent the to-sheet from inheriting compatible filters from the from-sheet.
     *
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all filters by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     *
     * @uxon-property inherit_filters
     * @uxon-type [all,none,matching_attributes]
     * @uxon-template none
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritFilters()
     */
    public function setInheritFilters($value) : DataSheetMapperInterface
    {
        switch (true) {
            case $value === true:
                $value = self::INHERIT_ALL;
                break;
            case $value === false:
                $value = self::INHERIT_NONE;
                break;
            default:
                $value = mb_strtolower($value);
                if (! defined('self::INHERIT_' . mb_strtoupper($value))) {
                    throw new DataMapperConfigurationError($this, 'Invalid value "' . $value . '" for data mapper option `inherit_filters`');
                }
                break;
        }
        if ($value !== self::INHERIT_NONE && $value !== self::INHERIT_MATCHING_ATTRIBUTES){
            if (! $this->canInheritFilters()) {
                throw new DataMapperConfigurationError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit filters from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritFilters = $value;
        return $this;
    }
    
    /**
     * Returns one of the INHERIT_X constants
     *
     * @return boolean
     */
    protected function getInheritSorters() : string
    {
        return $this->inheritSorters ?? ($this->canInheritSorters() ? self::INHERIT_ALL : self::INHERIT_NONE);
    }
    
    /**
     * Set to `none` to prevent the to-sheet from inheriting compatible sorters from the from-sheet.
     *
     * If the to-sheet is based on the same object as the from-sheet or a derivative,
     * the mapper will copy all sorters by default and apply the mapping afterwards.
     * This option can prevent this behavior.
     *
     * @uxon-property inherit_sorters
     * @uxon-type [all,none]
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritSorters()
     */
    public function setInheritSorters($value) : DataSheetMapperInterface
    {
        switch (true) {
            case $value === true:
                $value = self::INHERIT_ALL;
                break;
            case $value === false:
                $value = self::INHERIT_NONE;
                break;
            default:
                $value = mb_strtolower($value);
                if (! defined('self::INHERIT_' . mb_strtoupper($value))) {
                    throw new DataMapperConfigurationError($this, 'Invalid value "' . $value . '" for data mapper option `inherit_sorters`');
                }
                break;
        }
        if ($value !== self::INHERIT_NONE){
            if (! $this->canInheritSorters()) {
                throw new DataMapperConfigurationError($this, 'Data sheets of object "' . $this->getToMetaObject()->getAliasWithNamespace() . '" cannot inherit sorters from sheets of "' . $this->getFromMetaObject() . '"!');
            }
        }
        
        $this->inheritSorters = $value;
        return $this;
    }
    
    /**
     * Returns TRUE if columns of the from-sheet sheet can be inherited by the to-sheet.
     * 
     * @return boolean
     */
    protected function canInheritColumns() : bool
    {
        return $this->getToMetaObject()->is($this->getFromMetaObject());
    }
    
    /**
     * 
     * @return bool
     */
    protected function canInheritFilters() : bool
    {
        return $this->canInheritColumns();
    }
    
    /**
     * 
     * @return bool
     */
    protected function canInheritSorters() : bool
    {
        return $this->canInheritColumns();
    }
    
    /**
     *
     * @return bool
     */
    protected function getRefreshDataAfterMapping() : bool
    {
        return $this->refreshDataAfterMapping;
    }
    
    /**
     * Set to TRUE to read data after all mappings were performed.
     * 
     * @uxon-property refresh_data_after_mapping
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setRefreshDataAfterMapping()
     */
    public function setRefreshDataAfterMapping(bool $trueOrFalse) : DataSheetMapperInterface
    {
        $this->refreshDataAfterMapping = $trueOrFalse;
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
    
    /**
     * 
     * @return bool
     */
    protected function getReadMissingFromData() : bool
    {
        return $this->readMissingData;
    }
    
    /**
     * Set to FALSE to disable autoloading missing from-columns from the data source
     * 
     * @uxon-property read_missing_from_data
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $value
     * @return DataSheetMapper
     */
    protected function setReadMissingFromData(bool $value) : DataSheetMapper
    {
        $this->readMissingData = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    protected function getInheritEmptyData() : bool
    {
        return $this->inheritEmptyData;
    }
    
    /**
     * Set to TRUE to force the to-sheet to be empty if the from-sheet is empty
     * 
     * By default the to-sheet might still get new rows: e.g. if there are column-to-column mappings with
     * formulas. Setting `inherit_empty_data` to `true` will make sure, no new rows are created if the
     * from-sheet is empty. In this case, all changes to the data sheet structure (added columns, filters, etc.)
     * will still be applied - there will only be no rows if the from-sheet had none.
     * 
     * NOTE: you can still use `refresh_data_after_mapping` to read data into the to-sheet __after__
     * all mappings were performed.
     * 
     * @uxon-property inherit_empty_data
     * @uxon-type boolean
     * @uxon-default false
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::setInheritEmptyData()
     */
    public function setInheritEmptyData(bool $value) : DataSheetMapperInterface
    {
        $this->inheritEmptyData = $value;
        return $this;
    }
    
    /**
     * 
     * @return bool|NULL
     */
    protected function getRemoveDuplicateRows() : ?bool
    {
        return $this->removeDuplicateRows;
    }
    
    /**
     * Set to TRUE to force removal of duplicate rows or to FALSE to explicitly allow duplicates
     * 
     * By default, duplicate rows are removed automatically if the to-sheet has a filled UID column
     * and the rows really are equal. If there are rows with the same UID, but different values,
     * they will not be removed by default!
     * 
     * @uxon-property remove_duplicate_rows
     * @uxon-type bool
     * 
     * @param bool $value
     * @return DataSheetMapper
     */
    protected function setRemoveDuplicateRows(bool $value) : DataSheetMapper
    {
        $this->removeDuplicateRows = $value;
        return $this;
    }
    
    /**
     * Stores values from a column of the from-sheet in a context variable
     *
     * @uxon-property column_to_variable_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToVariableMapping[]
     * @uxon-template [{"from": "", "variable": ""}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToVariableMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnToVariableMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Places values of a context variable into a column of the to-sheet
     *
     * @uxon-property variable_to_column_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\VariableToColumnMapping[]
     * @uxon-template [{"variable": "", "to": ""}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setVariableToColumnMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new VariableToColumnMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetMapperInterface::getFromDataChecks()
     */
    public function getFromDataChecks() : array
    {
        $checks = [];
        foreach ($this->getMappings() as $mapping) {
            if ($mapping instanceof DataCheckMapping) {
                $checks = array_merge($checks, $mapping->getFromDataChecks());
            }
        }
        return $checks;
    }
    
    /**
     * Check from-data against these conditions before applying the mapper
     *
     * If any of these conditions are not met, the mapper will through an error. Each check may
     * contain it's own error message to make the errors better understandable for the user.
     *
     * @uxon-property from_data_invalid_if
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataCheck[]
     * @uxon-template [{"error_text": "", "operator": "AND", "conditions": [{"expression": "", "comparator": "", "value": ""}]}]
     *
     * @param UxonObject $arrayOfDataChecks
     * @return DataSheetMapper
     */
    protected function setFromDataInvalidIf(UxonObject $arrayOfDataChecks) : DataSheetMapper
    {
        $this->addMapping(new DataCheckMapping($this, new UxonObject([
            'from_data_invalid_if' => $arrayOfDataChecks
        ])));
        return $this;
    }
    
    /**
     * Stores values from a column of the from-sheet in a context variable
     *
     * @uxon-property json_to_rows_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\JsonToRowsMapping[]
     * @uxon-template [{"json_column": ""}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setJsonToRowsMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new JsonToRowsMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * Puts values of one or more columns in the from-sheet into a JSON inside a column of the to-sheet.
     *
     * @uxon-property column_to_json_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\DataColumnToJsonMapping[]
     * @uxon-template [{"from": "", "to": ""}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setColumnToJsonMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new DataColumnToJsonMapping($this, $prop));
        }
        return $this;
    }

    /**
     * Looks up a value in a separate data sheet and places it in the to-column
     *
     * @uxon-property lookup_mappings
     * @uxon-type \exface\Core\CommonLogic\DataSheets\Mappings\LookupMapping[]
     * @uxon-template [{"to": "// place looked up value in this column of the to-data", "lookup_object_alias": "// Look in this object", "lookup_column": "// Read this attribute or expression", "if_not_found": "leave_empty", "matches": [{"from": "// match from-data column", "lookup":"// to lookup object column"}]}]
     *
     * @param UxonObject $uxon
     * @return DataSheetMapperInterface
     */
    protected function setLookupMappings(UxonObject $uxon) : DataSheetMapperInterface
    {
        foreach ($uxon as $prop){
            $this->addMapping(new LookupMapping($this, $prop));
        }
        return $this;
    }
    
    /**
     * 
     * @param DataMappingInterface $mapping
     * @return string
     */
    protected function getMappingType(DataMappingInterface $mapping) : string
    {
        $class = PhpClassDataType::findClassNameWithoutNamespace(get_class($mapping));
        $name = StringDataType::convertCasePascalToUnderscore($class);
        $name = StringDataType::substringAfter($name, 'data_', $name);
        return $name;
    }
}