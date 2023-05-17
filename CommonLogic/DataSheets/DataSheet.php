<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Interfaces\DataSheets\DataSorterListInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetJoinError;
use exface\Core\Exceptions\DataSheets\DataSheetImportRowError;
use exface\Core\Exceptions\DataSheets\DataSheetWriteError;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetReadError;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\Exceptions\DataSheets\DataSheetDeleteError;
use exface\Core\Exceptions\Model\MetaObjectHasNoDataSourceError;
use exface\Core\Interfaces\Model\ConditionalExpressionInterface;
use exface\Core\CommonLogic\QueryBuilder\RowDataArraySorter;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;
use exface\Core\Interfaces\QueryBuilderInterface;
use exface\Core\Events\DataSheet\OnBeforeValidateDataEvent;
use exface\Core\Events\DataSheet\OnValidateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReadDataEvent;
use exface\Core\Events\DataSheet\OnReadDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeReplaceDataEvent;
use exface\Core\Events\DataSheet\OnReplaceDataEvent;
use exface\Core\Factories\RelationPathFactory;
use exface\Core\DataTypes\DataSheetDataType;
use exface\Core\DataTypes\RelationCardinalityDataType;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Model\ConditionInterface;
use exface\Core\DataTypes\EncryptedDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetInvalidValueError;
use exface\Core\Exceptions\DataSheets\DataSheetExtractError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\PhpClassDataType;

/**
 * Default implementation of DataSheetInterface
 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface
 * 
 * @author Andrej Kabachnik
 *        
 */
class DataSheet implements DataSheetInterface
{

    // properties to be copied on copy()
    private $cols = array();

    private $rows = array();

    private $totals_rows = array();

    private $filters = null;

    private $sorters = array();
    
    private $autosort = true;

    private $total_row_count = null;
    
    private $autocount = true;

    private $subsheets = array();

    private $aggregation_columns = null;
    
    private $aggregateAll = null;

    private $rows_on_page = null;

    private $row_offset = 0;

    private $uid_column_name = null;

    private $invalid_data_flag = false;
    
    private $is_fresh = true;

    // properties NOT to be copied on copy()
    private $exface;

    private $meta_object;
    
    private $dataSourceHasMoreRows = true;

    public function __construct(\exface\Core\Interfaces\Model\MetaObjectInterface $meta_object)
    {
        $this->exface = $meta_object->getModel()->getWorkbench();
        $this->meta_object = $meta_object;
        $this->filters = ConditionGroupFactory::createEmpty($this->exface, EXF_LOGICAL_AND, $this->getMetaObject());
        $this->cols = new DataColumnList($this->exface, $this);
        $this->aggregation_columns = new DataAggregationList($this->exface, $this);
        $this->sorters = new DataSorterList($this->exface, $this);
        // IDEA Can we use the generic EntityListFactory here or do we need a dedicated factory for subsheet lists?
        $this->subsheets = new DataSheetList($this->exface, $this);
    }

    /**
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::addRows($rows)
     */
    public function addRows(array $rows, bool $merge_uid_dublicates = false, bool $auto_add_columns = true) : DataSheetInterface
    {
        foreach ($rows as $row) {
            $this->addRow($row, $merge_uid_dublicates, $auto_add_columns);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::addRow()
     */
    public function addRow(array $row, bool $merge_uid_dublicates = false, bool $auto_add_columns = true, int $index = null) : DataSheetInterface
    {
        if (! empty($row)) {
            if ($merge_uid_dublicates === true && $this->hasUidColumn() === true && $uid = $row[$this->getUidColumn()->getName()]) {
                $uid_row_nr = $this->getUidColumn()->findRowByValue($uid);
                if ($uid_row_nr !== false) {
                    $this->rows[$uid_row_nr] = array_merge($this->rows[$uid_row_nr], $row);
                } else {
                    if ($index === null || is_numeric($index) === false) {
                        $this->rows[] = $row;
                    } else {
                        array_splice($this->rows, $index, 0, [$row]);
                    }
                }
            } else {
                if ($index === null || is_numeric($index) === false) {
                    $this->rows[] = $row;
                } else {
                    array_splice($this->rows, $index, 0, [$row]);
                }
            }
            
            // ensure, that all columns used in the rows are present in the data sheet
            if ($auto_add_columns === true) {
                $this->getColumns()->addMultiple(array_keys((array) $row));
            }
            $this->setFresh(true);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::joinLeft()
     */
    public function joinLeft(\exface\Core\Interfaces\DataSheets\DataSheetInterface $other_sheet, string $leftKeyColName = null, string $rightKeyColName = null, string $relation_path = '') : DataSheetInterface
    {
        // First copy the columns of the right data sheet ot the left one
        $right_cols = array();
        foreach ($other_sheet->getColumns() as $col) {
            $right_cols[] = $col->copy();
        }
        $this->getColumns()->addMultiple($right_cols, RelationPathFactory::createFromString($this->getMetaObject(), $relation_path));
        // Now process the data and join rows
        if (! is_null($leftKeyColName) && ! is_null($rightKeyColName)) {
            $addedRowsCnt = 0;
            foreach ($this->rows as $left_row_nr => $row) {
                // foreach() iterates over a COPY of the array, so $left_row_nr will always
                // be the number of the original left row! If rows are added while joining,
                // it must be increasead in order to match the index of that row in the new
                // data.
                $left_row_nr += $addedRowsCnt;
                // Check if the right column is really present in the data to be joined
                if (! $rCol = $other_sheet->getColumns()->get($rightKeyColName)) {
                    throw new DataSheetMergeError($this, 'Cannot find right key column "' . $rightKeyColName . '" for a left join!', '6T5E849');
                }
                // Find rows in the other sheet, that match the currently processed key
                $right_row_nrs = $rCol->findRowsByValue($row[$leftKeyColName]);
                if (false === empty($right_row_nrs)) {
                    // Since we do an OUTER JOIN, there may be multiple matching rows, so we need
                    // to loop through them. The first row is simply joined to the current left row
                    // (i.e. the columns of the other sheet are appended). For subsequent rows a
                    // copy of the left row is created and appended right next to it, than the
                    // right columns are appended to this row copy.
                    $needRowCopy = false;
                    $left_row_new_nr = null;
                    // Make sure to get the current state of the left row instead of using $row
                    // because theoretically it may have been changed already
                    $left_row = $row;
                    foreach ($right_row_nrs as $right_row_nr) {
                        if ($needRowCopy === true) {
                            $left_row_new_nr = ($left_row_new_nr ?? $left_row_nr) + 1;
                            $this->addRow($left_row, false, false, $left_row_new_nr);
                            $addedRowsCnt++;
                        }
                        $right_row = $other_sheet->getRow($right_row_nr);
                        foreach ($right_row as $col_name => $val) {
                            $this->setCellValue(RelationPath::relationPathAdd($relation_path, $col_name), ($left_row_new_nr ?? $left_row_nr), $val);
                        }
                        $needRowCopy = true;
                    }                    
                } else {
                    foreach ($right_cols as $col) {
                        $this->setCellValue(RelationPath::relationPathAdd($relation_path, $col->getName()), $left_row_nr, null);
                    }
                }
            }
        } elseif (is_null($leftKeyColName) && is_null($rightKeyColName)) {
            // TODO this only joins the first other sheet row. A real LEFT OUT JOIN would
            // need to dublicate rows as in the case above - but it's unclear, what should
            // happen if there are actually no key columns...
            foreach ($this->rows as $left_row_nr => $row) {
                $this->rows[$left_row_nr] = array_merge($row, $other_sheet->getRow($left_row_nr));
            }
        } else {
            throw new DataSheetJoinError($this, 'Cannot join data sheets, if only one key column specified!', '6T5V0GU');
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::importRows()
     */
    public function importRows(DataSheetInterface $other_sheet, bool $calculateFormulas = true)
    {
        if (! $this->getMetaObject()->is($other_sheet->getMetaObject()->getAliasWithNamespace())) {
            throw new DataSheetImportRowError($this, 'Cannot replace rows for object "' . $this->getMetaObject()->getAliasWithNamespace() . '" with rows from "' . $other_sheet->getMetaObject()->getAliasWithNamespace() . '": replacing rows only possible for compatible objects!', '6T5V1DR');
        }
        
        // Make sure, the UID is present in the result if it is there in the other sheet
        if (! $this->getUidColumn() && $other_sheet->getUidColumn()) {
            $uid_column = $other_sheet->getUidColumn()->copy();
            $this->getColumns()->add($uid_column);
        }
        
        // Make sure, all columns for system attributes are copied too
        foreach ($this->getMetaObject()->getAttributes()->getSystem() as $attr){
            if (! $this->getColumns()->getByAttribute($attr) && $col = $other_sheet->getColumns()->getByAttribute($attr)) {
                $sys_col = $col->copy();
                $this->getColumns()->add($sys_col);
            }
        }
        
        $columns_with_formulas = array();
        foreach ($this->getColumns() as $this_col) {
            // calculate formulas again, not regarding if the sheet importing to already has values
            if ($this_col->isFormula() && $calculateFormulas === true) {
                $columns_with_formulas[] = $this_col->getName();
                continue;
            }
            if ($other_col = $other_sheet->getColumn($this_col->getName())) {
                // TODO probably need to copy values to rows with matching UIDs instead of relying on identical sorting here
                if (count($this_col->getValues(false)) > 0 && count($this_col->getValues(false)) !== count($other_col->getValues(false))) {
                    throw new DataSheetImportRowError($this, 'Cannot replace rows of column "' . $this_col->getName() . '": source and target columns have different amount of rows!', '6T5V1XX');
                }
                $this_col->setValues($other_col->getValues(false));
            }
            // if the column is formula and still has empty values, add it to columns to be calculated again
            if ($this_col->isFormula() && $this_col->hasEmptyValues()) {                
                $columns_with_formulas[] = $this_col->getName();
            }
        }
        
        foreach ($columns_with_formulas as $name) {
            $this->getColumn($name)->setValuesByExpression($this->getColumn($name)->getFormula());
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getColumnValues()
     */
    public function getColumnValues(string $column_name, bool $include_totals = false) : array
    {
        $col = array();
        $rows = $include_totals ? array_merge($this->rows, $this->totals_rows) : $this->rows;
        foreach ($rows as $row_nr => $row) {
            $col[$row_nr] = $row[$column_name];
        }
        return $col;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setColumnValues()
     */
    public function setColumnValues(string $column_name, $column_values, $totals_values = null) : DataSheetInterface
    {
        // If the column is not yet there, add it, but make it hidden
        if (! $this->getColumn($column_name)) {
            $this->getColumns()->addFromExpression($column_name, null, true);
        }
        
        if (is_array($column_values)) {
            if ($this->countRows() > 0 && $this->countRows() !== count($column_values)) {
                throw new DataSheetRuntimeError($this, 'Cannot update ' . $this->countRows() . ' data rows with ' . count($column_values) . ' values: expecting as many values as rows or a single value to apply to all rows!');
            }
            // first update data rows
            foreach ($column_values as $row => $val) {
                $this->rows[$row][$column_name] = $val;
            }
        } else {
            foreach ($this->rows as $nr => $row) {
                $this->rows[$nr][$column_name] = $column_values;
            }
        }
        
        // if totals given, update the columns totals
        if ($totals_values) {
            foreach ($this->totals_rows as $nt => $row) {
                $this->totals_rows[$nt][$column_name] = (is_array($totals_values) ? $totals_values[$nt] : $totals_values);
            }
        }
        
        // Mark the column as up to date
        $this->getColumn($column_name)->setFresh(true);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getCellValue()
     */
    public function getCellValue(string $column_name, int $row_number)
    {
        if ($row = $this->rows[$row_number]) {
            return $row[$column_name];
        } elseif ($row_number >= $this->countRows()) {
            return $this->totals_rows[$row_number - $this->countRows()][$column_name];
        }
        return null;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setCellValue()
     */
    public function setCellValue(string $column_name, int $row_number, $value) : DataSheetInterface
    {
        // Create the column, if not already there
        if (! $this->getColumn($column_name)) {
            $this->getColumns()->addFromExpression($column_name);
        }
        
        // Detect, if the cell belongs to a total row
        $data_row_cnt = $this->countRows();
        $total_row_cnt = count($this->getTotalsRows());
        if ($row_number >= $data_row_cnt && $row_number < ($data_row_cnt + $total_row_cnt)) {
            $this->totals_rows[$row_number - $data_row_cnt][$column_name] = $value;
        }
        
        // Set the cell valu in the data matrix
        $this->rows[$row_number][$column_name] = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getTotalValue()
     */
    public function getTotalValue(string $column_name, int $row_number)
    {
        return $this->totals_rows[$row_number][$column_name];
    }

    /**
     *
     * @param DataColumnInterface $col            
     * @param \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder $query            
     */
    protected function dataReadAddColumnToQuery(DataColumnInterface $col, QueryBuilderInterface $query)
    {
        $sheetObject = $this->getMetaObject();
        // add the required attributes
        foreach ($col->getExpressionObj()->getRequiredAttributes() as $attr) {
            try {
                $attribute = $sheetObject->getAttribute($attr);
                $attribute_aggregator = DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $attr);
            } catch (MetaAttributeNotFoundError $e) {
                continue;
            }
            
            // If the sheet does not have such a column yet, add it as a hidden column. This means, that we
            // are dealing with an expression argument, that references a column, that is not explicitly
            // in the sheet.
            // Checking first, wether the column is a regular attribute reference, just prevents unneeded
            // searching through the columns. If the column represents an attribute, it is obvious that this
            // attribute is already in the sheet :)
            if (! $col->getExpressionObj()->isMetaAttribute() && ! $this->getColumns()->getByExpression($attr)) {
                $this->getColumns()->addFromExpression($attr, null, true);
            }
            
            // If the QueryBuilder for the current object can read the attribute, add it
            if ($query->canReadAttribute($attribute)) {
                $query->addAttribute($attr);
            } elseif (! $attribute->getRelationPath()->isEmpty()) {
                // If the query builder cannot read the attribute, make a subsheet and ultimately a separate query.
                // To create a subsheet we need to split the relation path to the current attribute into the part 
                // leading to the foreign key in the main data source and the part in the next data source. 
                // We always split into two parts by the first data source border: if there are more data sources 
                // involved, the subsheet will take care of splitting the rest of the path. 
                
                // Here is an example: Concider comparing turnover between the point-of-sale system and
                // the backend ERP. Each system shall have stores and their turnover in different data bases: 
                // TURNOVER<-POS->FLOOR->POS_STORE<->ERP_STORE<-ERP_STATS->TURNOVER. 
                // One way to make the comparison would be creating a data sheet for the POS object with one of 
                // the columns being FLOOR__POS_STORE__ERP_STORE__ERP_STATS__TURNOVER. This relation path will need to be split 
                // into FLOOR__POS_STORE__STORE_ID and ERP_STORE__ERP_STATS__TURNOVER. The first path will make 
                // sure, the main sheet will have a key to join the subsheet afterwards, while the second part will 
                // become on of the subsheet columns.
                // In the following code, this example would result in a subsheet based on the object ERP_STORE with
                // two columns: ID (of the ERP_STORE) and ERP_STATS__TURNOVER.                 
                
                // IDEA This will probably not work, if the relation path returns to some attribute of the initial data source. Is it possible at all?!
                /* @var $relPathToSubsheet \exface\Core\Interfaces\Model\MetaRelationPathInterface 
                 * In the above example, this would be FLOOR__POS_STORE__ERP_STORE
                 */
                $relPathToSubsheet = null;
                /* @var $relPathInParentSheet \exface\Core\Interfaces\Model\MetaRelationPathInterface 
                 * In the above example, this would be FLOOR__POS_STORE
                 */
                $relPathInParentSheet = RelationPathFactory::createForObject($sheetObject);
                /* @var $relPathInSubsheet \exface\Core\Interfaces\Model\MetaRelationPathInterface 
                 * In the above example, this would be ERP_STATS
                 */
                $relPathInSubsheet = null;
                
                // Loop through the relations in the path to the target attribute, to find the
                // data source border.
                /* @var $lastRelPath \exface\Core\Interfaces\Model\MetaRelationPathInterface */
                $lastRelPath = RelationPathFactory::createForObject($sheetObject);
                foreach ($attribute->getRelationPath()->getRelations() as $rel) {
                    $relPath = $lastRelPath->copy()->appendRelation($rel);
                    $relRightKey = $relPath->getAttributeOfEndObject($relPath->getRelationLast()->getRightKeyAttribute()->getAlias());
                    if (true === $query->canRead($relRightKey->getAliasWithRelationPath())) {
                        $relPathInParentSheet->appendRelation($rel);
                    } else {
                        if ($relPathToSubsheet === null) {
                            // Remember the path to the relation to the object of the other query
                            $relPathToSubsheet = $relPath->copy();
                            $relPathInSubsheet = RelationPathFactory::createForObject($relPathToSubsheet->getEndObject());
                        } else {
                            // All path parts following the one to the other data source, go into the subsheet
                            $relPathInSubsheet->appendRelation($rel);
                        }
                    }
                    $lastRelPath = $relPath;
                }
                
                // Determine the attribute alias for the subsheet
                // Also find out if we will need to aggregate the subsheet
                $subsheet_attribute_alias = $relPathInSubsheet->getAttributeOfEndObject($attribute->getAlias())->getAliasWithRelationPath();
                if ($attribute_aggregator) {
                    $subsheet_attribute_alias = DataAggregation::addAggregatorToAlias($subsheet_attribute_alias, $attribute_aggregator);
                    // If the attribute, we are looking for has an aggregator, we need to aggregate
                    // the subsheet over the key, that we are going to use for our join later on.
                    $needGroup = true;
                } else {
                    $needGroup = false;
                }
                
                // Create a subsheet for the relation if not yet existent and add the required attribute
                // NOTE: if we have multiple attributes to join via the same relation, we need to do it separately for
                // those with aggregations and those without. Aggregated subsheets will not be able to read non-aggregated
                // attributes. On the other hand, we can't aggregate the automatically as we do not really know, what this
                // will mean for the specific data.
                $subsheetId = $relPathToSubsheet->toString() . ($needGroup ? ':GROUPED' : '');
                if (! $subsheet = $this->getSubsheets()->get($subsheetId)) {
                    $subsheet_object = $relPathToSubsheet->getEndObject();
                    $parentSheetKeyAlias = $relPathInParentSheet->getAttributeOfEndObject($relPathToSubsheet->getRelationLast()->getLeftKeyAttribute()->getAlias())->getAliasWithRelationPath();
                    $subsheetKeyAlias = $relPathToSubsheet->getRelationLast()->getRightKeyAttribute()->getAlias();
                    $subsheet = DataSheetFactory::createSubsheet($this, $subsheet_object, $subsheetKeyAlias, $parentSheetKeyAlias, $relPathToSubsheet);
                    $this->getSubsheets()->add($subsheet, $subsheetId);
                    // Add the foreign key to the main query
                    // If the foreign key is calculated, add all attributes required to the query, otherwise just add
                    // the attribute itself
                    if (null !== $parentSheetKeyExpr = $this->getMetaObject()->getAttribute($parentSheetKeyAlias)->getCalculationExpression()) {
                        foreach ($parentSheetKeyExpr->getRequiredAttributes() as $alias) {
                            $query->addAttribute($alias);
                        }
                    } else {
                        $query->addAttribute($parentSheetKeyAlias);
                    }
                    // Also add the foreign key to this sheet
                    // IDEA do we need to add the column to the sheet? This is just useless data...
                    // Additionally it would make trouble when the column has formatters...
                    $this->getColumns()->addFromExpression($parentSheetKeyAlias, null, true);
                }
                
                // Add the current attribute to the subsheet prefixing it with it's relation path relative to the subsheet's object
                $subsheet->getColumns()->addFromExpression($subsheet_attribute_alias);
                
                // Add the related object key alias of the relation to the subsheet to that subsheet. This will be the right key in the future JOIN.
                $subsheet->getColumns()->addFromExpression($subsheet->getJoinKeyAliasOfSubsheet());
                
                // Aggregate of the right key of the future JOIN if there are attributes, that need aggregation
                if ($needGroup === true) {
                    $subsheet->getAggregations()->addFromString($subsheet->getJoinKeyAliasOfSubsheet()); 
                }
            } else {
                throw new DataSheetReadError($this, 'QueryBuilder "' . get_class($query) . '" cannot read attribute "' . $attribute->getAliasWithRelationPath() . '" of object "' . $attribute->getObject()->getAliasWithNamespace() .'"!');
            }
            
            if ($attribute->hasCalculation()) {
                foreach ($attribute->getCalculationExpression()->getRequiredAttributes() as $req) {
                    if (! $this->getColumn($req)) {
                        $column = $this->getColumns()->addFromExpression($req, '', true);
                        $this->dataReadAddColumnToQuery($column, $query);
                    }
                }
            }
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataRead()
     */
    public function dataRead(int $limit = null, int $offset = null) : int
    {
        $thisObject = $this->getMetaObject();
        
        if (is_null($limit)) {
            $limit = $this->getRowsLimit();
        }
        if (is_null($offset)) {
            $offset = $this->getRowsOffset();
        }
        
        $eventBefore = $this->getWorkbench()->eventManager()->dispatch(new OnBeforeReadDataEvent($this, $limit, $offset));
        if ($eventBefore->isPreventRead() === true) {
            return 0;
        }
        
        // Empty the data before reading
        // IDEA Enable incremental reading by distinguishing between reading the same page an reading a new page
        $this->removeRows();
        
        try {
            $query = $this->dataReadInitQueryBuilder($thisObject);
        } catch (DataSheetReadError $dsre) {
            throw $dsre;  
        } catch (\Throwable $e) {
            throw new DataSheetReadError($this, 'Cannot initialize query builder for object ' . $thisObject->__toString() . ': ' . $e->getMessage(), null, $e);
        }
        
        // set sorting
        $sorters = $this->getSorters();
        if ($sorters->isEmpty() && $this->getAutoSort() === true) {
            $sorters = $this->getMetaObject()->getDefaultSorters();
        }
        $postprocessorSorters = new DataSorterList($this->getWorkbench(), $this);
        foreach ($sorters as $sorter) {
            // If the sorter can be applied by the query, pass it to the query, otherwise
            // save it for a runtime sort after reading the data.
            if (! $query->canReadAttribute($thisObject->getAttribute($sorter->getAttributeAlias()))) {
                $postprocessorSorters->add($sorter);
            } else {
                if (! $postprocessorSorters->isEmpty()) {
                    throw new DataSheetReadError($this, 'Cannot apply sorter ' . $sorter . ' after ' . $postprocessorSorters->getLast() . ', because the latter cannot be performed by the data source and, thus, must be applied after reading data.');
                }
                $query->addSorter($sorter->getAttributeAlias(), $sorter->getDirection());
            }
        }
        
        if ($limit > 0) {
            $query->setLimit($limit, $offset);
        }
        
        try {
            $result = $query->read($thisObject->getDataConnection());
        } catch (\Throwable $e) {
            throw new DataSheetReadError($this, $e->getMessage(), null, $e);
        }
        
        $this->addRows($result->getResultRows());
        $this->totals_rows = $result->getTotalsRows();
        $this->total_row_count = $result->getAllRowsCounter();
        $this->dataSourceHasMoreRows = $result->hasMoreRows();
        
        // load data for subsheets if needed
        if ($this->isEmpty() === false) {
            foreach ($this->getSubsheets() as $subsheet) {
                // Add filter over parent keys
                $parentSheetKeyCol = $subsheet->getJoinKeyColumnOfParentSheet();
                // If the foreign key column is calculated, do the calculation first!
                if (null !== $parentSheetKeyExpr = $parentSheetKeyCol->getAttribute()->getCalculationExpression()) {
                    $this->setColumnValues($parentSheetKeyCol->getName(), $parentSheetKeyExpr->evaluate($this));
                }
                if ($subsheet->getJoinKeyColumnOfSubsheet()->isAttribute() && $subsheet->getJoinKeyColumnOfSubsheet()->getAttribute()->isReadable() === false) {
                    throw new DataSheetJoinError($this, 'Cannot join subsheet based on object "' . $subsheet->getMetaObject()->getName() . '" to data sheet of "' . $this->getMetaObject()->getName() . '": the subsheet\'s key column attribute "' . $subsheet->getJoinKeyColumnOfSubsheet()->getAttribute()->getName() . '" is not readable!');
                }
                
                // Make sure the subsheet inherits all the filters of this sheet that apply to
                // the subsheet's object (= start with the relation path to the subsheet)
                $subsheetRelPath = $subsheet->getRelationPathFromParentSheet()->toString();
                $foreign_keys = array_unique($parentSheetKeyCol->getValues(false));
                $foreign_keys = array_filter($foreign_keys, function($val) {return $val !== '' && $val !== null;});
                $subsheet->setFilters($this->getFilters()->rebase($subsheetRelPath, function(ConditionInterface $condition) use ($subsheetRelPath) {
                    return StringDataType::startsWith($condition->getAttributeAlias(), $subsheetRelPath . RelationPath::RELATION_SEPARATOR);
                }));
                // Also add a filter over the UIDs of this sheet for the later JOIN
                $subsheet->getFilters()->addConditionFromString($subsheet->getJoinKeyAliasOfSubsheet(), implode($parentSheetKeyCol->getAttribute()->getValueListDelimiter(), $foreign_keys), EXF_COMPARATOR_IN);
                
                // Do not sort subsheets and do not count data in data source!
                $subsheet->setAutoSort(false);
                $subsheet->setAutoCount(false);
                
                // Read data
                $subsheet->dataRead();
                // Do the JOIN
                $this->joinLeft($subsheet, $parentSheetKeyCol->getName(), $subsheet->getJoinKeyColumnOfSubsheet()->getName(), ($subsheet->hasRelationToParent() ? $subsheet->getRelationPathFromParentSheet()->toString() : ''));
            }
        }
        
        // Look for columns, that need calculation and perform that calculation
        foreach ($this->getColumns() as $name => $col) {
            switch (true) {
                case $col->getExpressionObj()->isFormula() === true:
                case $col->getExpressionObj()->isStatic() === true:
                    $expr = $col->getExpressionObj();
                    break;
                case $col->isAttribute() && $col->getAttribute()->hasCalculation():
                    $expr = $col->getAttribute()->getCalculationExpression();
                    break;
                default:
                    continue 2;
            }
            
            $vals = $expr->evaluate($this);
            if (is_array($vals)) {
                // See if the expression returned more results, than there were rows. If so, it was also performed on
                // the total rows. In this case, we need to slice them off and pass to set_column_values() separately.
                // This only works, because evaluating an expression cannot change the number of data rows! This justifies
                // the assumption, that any values after count_rows() must be total values.
                if ($this->countRows() < count($vals)) {
                    $totals = array_slice($vals, $this->countRows());
                    $vals = array_slice($vals, 0, $this->countRows());
                }
            }
            $this->setColumnValues($name, $vals, $totals);
        }
        
        if (! $postprocessorSorters->isEmpty()) {
            if ($this->isPaged() === true) {
                throw new DataSheetReadError($this, 'Cannot sort by columns from different data sources when using pagination: either increase page length or filter data to fit on one page!');
            }
            $this->sort($postprocessorSorters);
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new onReadDataEvent($this));
        return $result->getAffectedRowsCounter();
    }
    
    protected function dataReadInitQueryBuilder(MetaObjectInterface $object) : QueryBuilderInterface
    {
        if ($object->isReadable() === false) {
            throw new DataSheetReadError($this, 'Cannot read data for object ' . $object->getAliasWithNamespace() . ': object is marked as not readable in model!', '73H79S1');
        }
        
        // create new query for the main object
        $query = QueryBuilderFactory::createForObject($object);
        
        foreach ($this->getColumns() as $col) {
            if ($col->getExpressionObj()->isMetaAttribute() === true && $col->getAttribute()->isReadable() === false) {
                continue;
            }
            $this->dataReadAddColumnToQuery($col, $query);
            foreach ($col->getTotals()->getAll() as $row => $total) {
                $query->addTotal($col->getAttributeAlias(), $total->getAggregator(), $row);
            }
        }
        
        // Ensure, the columns with system attributes are always in the select if a row represents a
        // single UID. Adding system attributes does not make sense for aggregated rows as it is 
        // unclear, how they should be aggregated.
        //
        // FIXME With growing numbers of behaviors and system attributes, this becomes a pain, as more and more possibly
        // aggregated columns are added automatically - even if the sheet is only meant for reading. Maybe we should let
        // the code creating the sheet add the system columns. The behaviors will prduce errors if this does not happen anyway.
        if ($this->hasAggregateAll() === false) {
            foreach ($object->getAttributes()->getSystem()->getAll() as $attr) {
                if (! $this->getColumns()->getByAttribute($attr)) {
                    // Check if the system attribute has a default aggregator if the data sheet is being aggregated
                    if ($this->hasAggregations() && $attr->getDefaultAggregateFunction()) {
                        $col = $this->getColumns()->addFromExpression($attr->getAlias() . DataAggregation::AGGREGATION_SEPARATOR . $attr->getDefaultAggregateFunction(), null, true);
                    } else {
                        $col = $this->getColumns()->addFromAttribute($attr, true);
                    }
                    $this->dataReadAddColumnToQuery($col, $query);
                }
            }
        }
        
        // Look for conditions based on related expressions that cannot be read by the query and try to get 
        // their values here by reading them separately. 
        $foreignConditions = [];
        foreach ($this->getFilters()->getConditionsRecursive() as $cond) {
            if ($cond->getExpression()->isMetaAttribute()) {
                $condAttr = $cond->getExpression()->getAttribute();
                if (! $condAttr->getRelationPath()->isEmpty() && ! $query->canReadAttribute($condAttr) && ! $cond->isEmpty()) {
                    $foreignConditions[] = $cond;
                }
            }
        }
        // If there are no foreign conditions, just pass the filters of the data sheet to the query.
        // If foreign conditions are found, replace them with IN conditions on the foreign key in this
        // object (the relations left key attribute) having explicit key values. These key values
        // are read separately in the foreach() below. This should even work for relations over multiple
        // data sources as the $condDS might again use this technique resolve its foreign conditions, etc.
        if (empty($foreignConditions)) {
            $queryFilters = $this->getFilters();
        } else {
            $queryFilters = $this->getFilters()->copy();
            foreach ($foreignConditions as $foreignCond) {
                /* @var $cond \exface\Core\CommonLogic\Model\Condition */
                foreach ($queryFilters->getConditionsRecursive() as $cond) {
                    if ($cond->exportUxonObject()->toArray() === $foreignCond->exportUxonObject()->toArray()) {
                        $condAttr = $cond->getExpression()->getAttribute();
                        $condRelPath = $condAttr->getRelationPath();
                        if (! $condRelPath->isEmpty()) {
                            $condRel = $condRelPath->getRelationFirst();
                            $condDS = DataSheetFactory::createFromObject($condRel->getRightObject());
                            $condCol = $condDS->getColumns()->addFromAttribute($condRel->getRightKeyAttribute());
                            $condDS->getFilters()->addConditionFromExpression($cond->getExpression()->rebase($condRel->getAliasWithModifier()), $cond->getValue(), $cond->getComparator());
                            $condDS->dataRead();
                            $newCond = ConditionFactory::createFromAttribute($condRel->getLeftKeyAttribute(), implode($condAttr->getValueListDelimiter(), array_filter(array_unique($condCol->getValues()))), ComparatorDataType::IN);
                            $queryFilters->replaceCondition($cond, $newCond);
                        }
                    }
                }
            }
        }
        
        // Set explicitly defined filters
        $query->setFiltersConditionGroup($queryFilters);
        // Add filters from the contexts
        foreach ($this->exface->getContext()->getScopeApplication()->getFilterContext()->getConditions($object) as $cond) {
            $query->addFilterCondition($cond);
        }
        
        // set aggregations
        foreach ($this->getAggregations() as $aggr) {
            if (! $query->canReadAttribute($object->getAttribute($aggr->getAttributeAlias()))) {
                throw new DataSheetReadError($this, 'Cannot apply aggregation "' . $aggr->getAttributeAlias() . '": Aggregations over attributes in other data sources, than the man sheet object are currently not supported!');
            }
            $query->addAggregation($aggr->getAttributeAlias());
        }
        
        return $query;
    }

    public function countRows() : int
    {
        return count($this->rows);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataSave()
     */
    public function dataSave(DataTransactionInterface $transaction = null)
    {
        if ($this->hasUidColumn(false) === true) {
            return $this->dataUpdate(true, $transaction);
        } else {
            return $this->dataCreate(true, $transaction);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataUpdate()
     */
    public function dataUpdate(bool $create_if_uid_not_found = false, DataTransactionInterface $transaction = null) : int
    {
        if ($this->getMetaObject()->isWritable() === false) {
            throw new DataSheetWriteError($this, 'Cannot update data for object ' . $this->getMetaObject()->getAliasWithNamespace() . ': object is not writeable!', '70Y6HAK');
        }
        
        $counter = 0;
        
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        // Fire OnBeforeUpdateDataEvent to allow additional checks, manipulations or custom update logic
        $eventBefore = $this->getWorkbench()->eventManager()->dispatch(new OnBeforeUpdateDataEvent($this, $transaction, $create_if_uid_not_found));
        if ($eventBefore->isPreventUpdate() === true) {
            if ($commit && ! $transaction->isRolledBack()) {
                $transaction->commit();
            }
            return $this->countRows();
        }
        
        // Check if the data source already contains rows with matching UIDs
        // TODO do not update rows, that were created here. Currently they are created and immediately updated afterwards.
        if ($create_if_uid_not_found === true) {
            if ($uidCol = $this->getUidColumn()) {
                // Find rows, that do not have a UID value
                $emptyUidRows = $uidCol->findRowsByValue('');
                // Create another data sheet selecting those UIDs currently present in the data source
                $uid_check_ds = DataSheetFactory::createFromObject($this->getMetaObject());
                $uid_check_ds->getColumns()->add($uidCol->copy());
                $uid_check_ds->getFilters()->addConditionFromColumnValues($uidCol);
                $uid_check_ds->dataRead();
                $missing_uids = $uidCol->diffValues($uid_check_ds->getUidColumn());
                // Filter away empty UID values, because we already have the in $emptyUidRows
                $missing_uids = array_filter($missing_uids);
                if (! empty($missing_uids) || ! empty($emptyUidRows)) {
                    // Create a separated data sheet for the new rows
                    $create_ds = $this->copy()->removeRows();
                    // For non-empty missing UIDs just add the entire row
                    foreach ($missing_uids as $missing_uid) {
                        $create_ds->addRow($this->getRowByColumnValue($uidCol->getName(), $missing_uid));
                    }
                    // For rows with empty UIDs we will need to remember the index of
                    // each row, so we can update it with the created UID once we've received
                    // them back from the data source.
                    $emptyUidRowsInCreateSheet = [];
                    foreach ($emptyUidRows as $newRowNr) {
                        // Since we may alread have rows in the $create_ds, that have non-empty
                        // UIDs, we need get the exact index of the inserted row (not just
                        // count them starting with 0) - that is the next index since rows
                        // are allways numbered sequentially.
                        $emptyUidRowsInCreateSheet[] = $create_ds->countRows();
                        $create_ds->addRow($this->getRow($newRowNr));
                    }
                    try {
                        $counter += $create_ds->dataCreate(false, $transaction);
                        // Now update the columns of the original sheet with values from the create-sheet
                        // on all rows, that previously did not have a UID value. Doing this for all
                        // mutual columns instead of just the UID ensures, that default values and
                        // those altered by behaviors are not lost
                        foreach ($create_ds->getColumns() as $create_col) {
                            if ($col = $this->getColumns()->getByExpression($create_col->getExpressionObj())) {
                                foreach ($emptyUidRowsInCreateSheet as $i => $r) {
                                    $col->setValue($emptyUidRows[$i], $create_col->getCellValue($r));
                                }
                            }
                        }
                    } catch (DataSheetMissingRequiredValueError | DataSheetInvalidValueError $e) {
                        // If the create-operation failed due to missing values, we will need to
                        // tell the user where they are in the original sheet (as the user does not
                        // know anything about our additional create-sheet!). Here we calculate
                        // the original sheet's row numbers an rethrow the exception with these
                        if (null !== $brokenRowsInCreateSheet = $e->getRowIndexes()) {
                            $brokenRowIdxs = array_map(function(int $createRowIdx) use ($emptyUidRowsInCreateSheet, $emptyUidRows) { 
                                return $emptyUidRows[array_search($createRowIdx, $emptyUidRowsInCreateSheet)]; 
                            }, $brokenRowsInCreateSheet);
                        }
                        $eClass = get_class($e);
                        throw new $eClass($this, null, null, $e, $e->getColumnName(), $brokenRowIdxs);
                    }
                }
            } else {
                throw new DataSheetWriteError($this, 'Creating rows from an update statement without a UID-column not supported yet!', '6T5VBHF');
            }
        }
        
        // After all preparation is done, check to see if there are any rows to update left
        if ($this->isEmpty()) {
            return 0;
        }
        
        // Add columns with fixed values to the data sheet
        $processed_relations = array();
        foreach ($this->getColumns() as $col) {
            if (! $col->getAttribute()) {
                // throw new MetaAttributeNotFoundError($this->getMetaObject(), 'Cannot find attribute for data sheet column "' . $col->getName() . '"!');
                continue;
            }
            
            // Fetch all attributes with fixed values and add them to the sheet if not already there
            
            // If it's a column with a related attribute, we only need to process the relation
            // once, so skip already processed relations at this point.
            $rel_path = $col->getAttribute()->getRelationPath()->toString();
            if ($processed_relations[$rel_path]) {
                continue;
            }
            
            // If there is a relation path, but the column contains subsheets, it's data will
            // be treated in the subsheet, so we don't need to process it here.
            if ($col->getDataType() instanceof DataSheetDataType) {
                continue;
            }
            
            // Since updating an attribute also means updating the corresponding object, we need
            // to apply fixed values to every attribute of the object. Note, that the updated
            // attribute may be a related one, so we need to add fixed attributes of it's (related)
            // object.
            /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
            foreach ($col->getAttribute()->getObject()->getAttributes() as $attr) {
                if ($fixedExpr = $attr->getFixedValue()) {
                    $alias_with_relation_path = RelationPath::relationPathAdd($rel_path, $attr->getAlias());
                    if (! $fixedCol = $this->getColumn($alias_with_relation_path)) {
                        $fixedCol = $this->getColumns()->addFromExpression($alias_with_relation_path, NULL, true);
                    } elseif ($fixedCol->getIgnoreFixedValues()) {
                        continue;
                    }
                    $fixedCol->setValuesByExpression($fixedExpr);
                }
            }
            $processed_relations[$rel_path] = true;
        }
        
        // Create a query
        $query = QueryBuilderFactory::createForObject($this->getMetaObject());
        // Add filters to the query
        $query->setFiltersConditionGroup($this->getFilters());
        
        // Add values to the query
        // At this point, it is important to understand, that there are different types of update data sheets possible:
        // - A "regular" sheet with one row per object identified by the UID column. In this case, that object needs to be updated by values from
        // the corresponding columns
        // - A data sheet with a single row and no UID column, where the values of that row should be saved to all object matching the filter
        // - A data sheet with a single row and a UID column, where the one row references multiple object explicitly selected by the user (the UID
        // column will have one cell with a list of UIDs in this case.
        $sheetHasUidValues = $this->hasUidColumn(true);
        foreach ($this->getColumns() as $col) {
            if (! $col->getExpressionObj()->isMetaAttribute()) {
                // Skip columns, that do not represent a meta attribute
                continue;
            } 
            
            $columnAttr = $col->getAttribute();
            switch (true) {
                case $columnAttr->isWritable() === false && ($this->hasUidColumn() === true && $col === $this->getUidColumn()) === false:
                    // Skip read-only attributes unless it is the UID column (which will be used as a filter later on)
                    continue 2;
                case $col->getDataType() instanceof DataSheetDataType:
                    // Update nested sheets - i.e. replace all rows in the data source, that are related to
                    // the each row of the main sheet with the nested rows here.
                    $this->dataUpdateNestedSheets($col, $create_if_uid_not_found, $transaction);
                    continue 2;                
                case DataAggregation::getAggregatorFromAlias($this->getWorkbench(), $col->getExpressionObj()->toString()):
                    // Skip columns with aggregate functions
                    continue 2;
            }
            
            // If the column represents a required attribute, check if all rows have values.
            // If not, make sure empty values are ignored (cannot empty a required field!)
            $ignoreEmptyValues = false;
            if ($columnAttr->isRequired() === true && $col->hasEmptyValues() === true) {
                $ignoreEmptyValues = true;
            }
            
            // Use the UID column as a filter to make sure, only these rows are affected
            if ($columnAttr->getAliasWithRelationPath() === $this->getMetaObject()->getUidAttributeAlias()) {
                $uidAttr = $this->getMetaObject()->getUidAttribute();
                if (! $col->isEmpty(true)) {
                    $query->addFilterFromString($uidAttr->getAlias(), implode($uidAttr->getValueListDelimiter(), array_unique($col->getValues(false))), EXF_COMPARATOR_IN);
                }
                // Do not update the UID attribute if it is neither editable nor required
                if ($uidAttr->isEditable() === false && $uidAttr->isRequired() === false) {
                    continue;
                }
            }
            
            // Add all other columns to values
            // First check, if the attribute belongs to a related object
            if ($rel_path = $columnAttr->getRelationPath()->toString()) {
                if ($this->getMetaObject()->getRelation($rel_path)->isForwardRelation()) {
                    $uid_column_alias = $rel_path;
                } else {
                    // $uid_column = $this->getColumn($this->getMetaObject()->getRelation($rel_path)->getLeftKeyAttribute()->getAliasWithRelationPath());
                    throw new DataSheetWriteError($this, 'Updating attributes from reverse relations ("' . $col->getExpressionObj()->toString() . '") is not supported yet!', '6T5V4HW');
                }
            } else {
                $uid_column_alias = $this->getMetaObject()->getUidAttributeAlias();
            }
            
            // Now we know, the column represents a direct attribute. So add it to the query
            
            // If the data sheet has separate values per row (identified by the UID column), add all the values 
            // to the query. In this case, each object will get its own value.
            if ($sheetHasUidValues) {
                // However, we need to ensure, that there are UIDs for each value, even if the value belongs to a related object. 
                // If there is no appropriate UID column for updated related object, the UID values must be fetched from the data 
                // source using an identical data sheet, but having only the required uid column. Since the new data sheet is 
                // cloned, it will have exactly the same filters, order, etc. so we can be sure to fetch only those UIDs, that 
                // should have been in the original sheet. Additionally we need to add a filter over the values of the original 
                // UID column, in case the user had explicitly selected some of the rows of the original data set.
                if (! $colObjectUidColumn = $this->getColumns()->getByExpression($uid_column_alias)) {
                    $uid_data_sheet = $this->copy();
                    $uid_data_sheet->getColumns()->removeAll();
                    $uid_data_sheet->getColumns()->addFromExpression($this->getMetaObject()->getUidAttributeAlias());
                    $uid_data_sheet->getColumns()->addFromExpression($uid_column_alias);
                    $uid_data_sheet->getFilters()->addConditionFromString($this->getMetaObject()->getUidAttributeAlias(), implode($this->getUidColumn()->getAttribute()->getValueListDelimiter(), $this->getUidColumn()->getValues()), EXF_COMPARATOR_IN);
                    $uid_data_sheet->dataRead();
                    $colObjectUidColumn = $uid_data_sheet->getColumn($uid_column_alias);
                }
                
                $values = $col->getValuesNormalized();
                $uids = $colObjectUidColumn->getValues(false);
                if ($ignoreEmptyValues) {
                    $columnTyle = $col->getDataType();
                    foreach ($values as $r => $val) {
                        if ($columnTyle->isValueEmpty($val)) {
                            unset($values[$r]);
                            unset($uids[$r]);
                        }
                    }
                }
                // add values to query if not empty by now
                if(!empty($values)) {
                    $query->addValues($col->getExpressionObj()->toString(), $values, $uids);
                }
            } else {
                // If there is only one value for the entire data sheet (no UIDs gived), add it to the query as a single column value.
                // In this case all object matching the filter will get updated by this value
                $query->addValue($col->getExpressionObj()->toString(), $col->getValuesNormalized()[0]);
            }
        }
        
        // Run the query
        $connection = $this->getMetaObject()->getDataConnection();
        $transaction->addDataConnection($connection);
        try {
            $result = $query->update($connection);
            $counter += $result->getAffectedRowsCounter();
        } catch (\Throwable $e) {
            $transaction->rollback();
            $commit = false;
            throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), null, $e);
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        if ($result->getAllRowsCounter() !== null) {
            $this->setCounterForRowsInDataSource($result->getAllRowsCounter());
        } elseif ($result->hasMoreRows() === false) {
            $this->setCounterForRowsInDataSource($this->countRows());
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnUpdateDataEvent($this, $transaction));
        
        return $counter;
    }
    
    /**
     * Update nested data for a main sheet column column with nested data sheets.
     * 
     * Replaces all rows in the data source, that are related to the each row of the 
     * main sheet with the nested rows here.
     * 
     * @param DataColumnInterface $column
     * @param DataTransactionInterface $transaction
     * 
     * @throws InvalidArgumentException
     * @throws DataSheetWriteError
     * 
     * @return int
     */
    protected function dataUpdateNestedSheets(DataColumnInterface $column, bool $createIfUidNotFound, DataTransactionInterface $transaction) : int
    {
        $counter = 0;
        
        if (! ($column->getDataType() instanceof DataSheetDataType)) {
            throw new InvalidArgumentException('Cannot update nested data for data sheet column "' . $column->getName() . '": invalid column data type "' . $column->getDataType()->getAliasWithNamespace() . '"! Expecting type "exface.Core.DataSheet" or a derivative!');
        }
        
        foreach ($column->getValues(false) as $rowNr => $sheetArr) {
            if (! $sheetArr) {
                continue;
            }
            
            // Use the dataReplaceByFilters() method to do the replacement. This will ensure, that
            // removed rows will be deleted from the data source - ultimately removing all rows
            // if the new nested sheet is empty.
            // Using dataReplaceByFilters() requires, that there is a filter over the relation to
            // the main sheet and that every nested row has a value in the foreign-key column of
            // that relation. The latter is actually only required if we are going to create new
            // rows, but at this point, we don't know, if this is going to be neccessary. On the
            // other hand, adding a key-column to every row also makes sure, they all really do belong
            // the main sheet's row.
            $relPathToNestedSheet = RelationPathFactory::createFromString($this->getMetaObject(), $column->getAttributeAlias());
            $relPathFromNestedSheet = $relPathToNestedSheet->reverse();
            $relThisSheetKeyAttr = $relPathFromNestedSheet->getRelationLast()->getRightKeyAttribute();
            $relThisSheetKeyCol = $this->getColumns()->getByAttribute($relThisSheetKeyAttr);
            $relThisKeyVal = $relThisSheetKeyCol->getCellValue($rowNr);
            if (! $relThisSheetKeyCol || $relThisKeyVal === '' || $relThisKeyVal === null) {
                throw new DataSheetWriteError($this, 'Cannot update nested data - missing key value in main data sheet!');
            }
            
            // Instantiate a subsheet from the value
            $nestedSheet = DataSheetFactory::createSubsheetFromUxon(
                $this, // parent
                UxonObject::fromAnything($sheetArr), // subsheet UXON
                $relPathFromNestedSheet->toString(), // JOIN key alias in subsheet
                $relThisSheetKeyCol->getAttributeAlias(), // JOIN key alias in parent
                $relPathToNestedSheet //relation path from parent sheet to nested sheet
            );
            
            $nestedSheet->getFilters()->addConditionFromString($relPathFromNestedSheet->toString(), $relThisKeyVal, ComparatorDataType::EQUALS);
            
            // If the nested data is empty (or even has rows, but no values), simply delete any nested data
            // Don't process this subesheet any further as the next step would add a relation column, which would
            // make the sheet not empty anymore. Continue with the next subsheet.
            if ($nestedSheet->isEmpty(true)) {
                $counter += $nestedSheet->dataDelete($transaction); 
                continue;
            }
            
            // Add a column with the relation to the parent sheet
            if (! $relNestedSheetCol = $nestedSheet->getColumns()->getByExpression($relPathFromNestedSheet->toString())) {
                $relNestedSheetCol = $nestedSheet->getColumns()->addFromExpression($relPathFromNestedSheet->toString());
            }
            $relNestedSheetCol->setValues($relThisKeyVal);
            
            if (! $nestedSheet->hasUidColumn(true) && $nestedSheet->getMetaObject()->hasUidAttribute()) {
                // If the nested sheet has data, try to find the corresponding UID values in the data source
                if (! $nestedSheet->isEmpty() && $nestedSheet->getMetaObject()->isReadable()) {
                    // Add a UID column to the original nested sheet to store looked up values
                    // Need to add it here explicitly to make sure it exists even if we don't find
                    // any UIDs, which would mean all nested rows are new ones!
                    $nestedSheet->getColumns()->addFromUidAttribute();
                    
                    // Make a copy of the nested sheet
                    $nestedUidSheet = $nestedSheet->copy();
                    // Add the UID column
                    $nestedUidSheet->getColumns()->addFromUidAttribute();
                    // Add filters for every column in the original nested sheet
                    foreach ($nestedSheet->getColumns() as $col) {
                        // Skip the column with the relation to the main heet because we
                        // added filters for it in the previous step
                        if ($relNestedSheetCol === $col) {
                            continue;
                        }
                        $nestedUidSheet->getFilters()->addConditionFromColumnValues($col);
                    }
                    // Read the data
                    $nestedUidSheet->dataRead();
                    // Now we have read all rows from the data source, that have the same values as
                    // the nested sheet in all columns except the UID
                    // NOTE: the read data might also contain other system colums!
                    $nestedUidColName = $nestedUidSheet->getUidColumnName();
                    foreach ($nestedUidSheet->getRows() as $nestedUidRow) {
                        $nestedUid = $nestedUidRow[$nestedUidColName];
                        // Ignore empty UIDs. This has been the case, when the child object has an SQL_READ_FROM
                        // property that is a view listing all possible values without UIDs. This approach can
                        // be used to create checklists.
                        if ($nestedUid === null || $nestedUid === '') {
                            continue;
                        }
                        // Now find rows in the original nested sheet, that have the same values
                        // as this rows of the read sheet and give them the UID of this freshly read row.
                        // To do this we strip off everything from the row, that is not present in
                        // the original sheet and the UID value of course too.
                        unset($nestedUidRow[$nestedUidColName]);
                        foreach (array_keys($nestedUidRow) as $cn) {
                            if (! $nestedSheet->getColumns()->get($cn)) {
                                unset($nestedUidRow[$cn]);
                            }
                        }
                        // Search for matching rows of the original sheet
                        $nestedSheetIdxs = $nestedSheet->findRowsByValues($nestedUidRow);
                        // If it's a single row exactly - we would end up with non-unique UIDs which is bad...
                        if ((empty($nestedSheetIdxs) && $createIfUidNotFound === false) || count($nestedSheetIdxs) > 1) {
                            throw new DataSheetWriteError($this, 'Cannot process subsheet for "' . $column->getAttributeAlias() . '": UID count mismatch!');
                        }
                        // If everything worked well, put the UID into the original nested sheet
                        if (! empty($nestedSheetIdxs)) {
                            $nestedSheet->setCellValue($nestedUidColName, $nestedSheetIdxs[0], $nestedUid);
                        }
                    }
                }
            } // end if no UID column
            
            $counter += $nestedSheet->dataReplaceByFilters($transaction, true, false);
        } // Continue with the next subsheet
        
        return $counter;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataReplaceByFilters()
     */
    public function dataReplaceByFilters(DataTransactionInterface $transaction = null, bool $delete_redundant_rows = true, bool $update_by_uid_ignoring_filters = true) : int
    {
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        $eventBefore = $this->getWorkbench()->eventManager()->dispatch(new OnBeforeReplaceDataEvent($this, $transaction));
        if ($eventBefore->isPreventReplace() === true) {
            return 0;
        }
        
        $deleteCnt = 0;
        $updateCnt = 0;
        if ($delete_redundant_rows) {
            
            // Can't delete if not filters set, as there is no way to diff sheet and data source.
            if ($this->getFilters()->isEmpty() === true) {
                throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data if no filter are defined! This would delete ALL data for the object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', '6T5V4TS');
            }
            
            // If thee sheet is empty, we simply need to delete everything matching the filter
            // - that's it, no need to do anything else.
            if ($this->isEmpty(true) === true) {
                return $this->dataDelete($transaction);
            } 
            
            // No we know, the sheet has filters and rows, so let's proceed with diffing.
            if ($this->hasUidColumn() === true) {
                $redundant_rows_ds = $this->copy();
                $redundant_rows_ds->getColumns()->removeAll();
                $uid_column = $this->getUidColumn()->copy();
                $redundant_rows_ds->getColumns()->add($uid_column);
                $redundant_rows_ds->dataRead();
                $redundant_rows = $redundant_rows_ds->getUidColumn()->diffValues($this->getUidColumn());
                if (! empty($redundant_rows)) {
                    $delete_ds = DataSheetFactory::createFromObject($this->getMetaObject());
                    $delete_col = $uid_column->copy();
                    $delete_ds->getColumns()->add($delete_col);
                    $delete_ds->getUidColumn()->removeRows()->setValues(array_values($redundant_rows));
                    $deleteCnt += $delete_ds->dataDelete($transaction);
                }
            } else {
                throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data for "' . $this->getMetaObject()->getAliasWithNamespace() . '": data sheet has no UID, so there is no way to compare its rows to the data source reliably.', '6T5V5EB');
            }
        }
        
        // If we need to update records by UID and we have a non-empty UID column, we need to remove all filters to make sure the update
        // runs via UID only. Thus, the update is being performed on a copy of the sheet, which does not have any filters. In all other
        // cases, the update should be performed on the original data sheet itself.
        if ($update_by_uid_ignoring_filters === true && $this->hasUidColumn(true) === true) {
            $update_ds = $this->copy();
            $update_ds->getFilters()->removeAll();
        } else {
            $update_ds = $this;
        }
        
        $updateCnt = $update_ds->dataUpdate(true, $transaction);
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnReplaceDataEvent($this, $transaction));
        
        return $deleteCnt+$updateCnt;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataCreate()
     */
    public function dataCreate(bool $update_if_uid_found = true, DataTransactionInterface $transaction = null) : int
    {
        $thisObj = $this->getMetaObject();
        if ($thisObj->isWritable() === false) {
            throw new DataSheetWriteError($this, 'Cannot create data for object ' . $thisObj->getAliasWithNamespace() . ': object is not writeable!', '70Y6HAK');
        }
        
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        $eventBefore = $this->getWorkbench()->eventManager()->dispatch(new OnBeforeCreateDataEvent($this, $transaction, $update_if_uid_found));
        if ($eventBefore->isPreventCreate() === true) {
            if ($commit && ! $transaction->isRolledBack()) {
                $transaction->commit();
            }
            return $this->countRows();
        }
        
        // Create a query
        $query = QueryBuilderFactory::createForObject($thisObj);
        $nestedSheetCols = [];
        
        // Add values for columns based on attributes with defaults or fixed values
        foreach ($thisObj->getAttributes()->getAll() as $attr) {
            if ($def = ($attr->getDefaultValue() ? $attr->getDefaultValue() : $attr->getFixedValue())) {
                if (! $col = $this->getColumns()->getByAttribute($attr)) {
                    $col = $this->getColumns()->addFromExpression($attr->getAlias());
                }
                try {
                    $col->setValuesFromDefaults();
                } catch (DataSheetRuntimeError $e) {
                    throw new DataSheetWriteError($this, 'Failed to create object "' . $thisObj->getName() . '" (' . $thisObj->getAliasWithNamespace() . '): missing values for required attribute "' . $attr->getName() . '" (alias ' . $attr->getAliasWithRelationPath() . ') on row(s) ' . implode(', ', $col->findEmptyRows()) . '!', null, $e);
                }
            }
        }
        
        // Check, if all required attributes are present
        foreach ($thisObj->getAttributes()->getRequired() as $req) {
            // Skip read-only attributes. They can also be marked as required if the are allways present, but
            // since they are not writeable, we cannot explicitly create tehm.
            if ($req->isWritable() === false) {
                continue;
            }
            
            if (! $req_col = $this->getColumns()->getByAttribute($req)) {
                // If there is no column for the required attribute, add one
                $req_col = $this->getColumns()->addFromExpression($req->getAlias());
                // First see if there are default values for this column
                if ($def = ($req->getDefaultValue() ? $req->getDefaultValue() : $req->getFixedValue())) {
                    $req_col->setValuesByExpression($def);
                } else {
                    // Try to get the value from the current filter contexts: if the missing attribute was used as a direct filter, we assume, that the data is saved
                    // in the same context, so we can set the attribute value to the filter value
                    // TODO Look in other context scopes, not only in the application scope. Still looking for an elegant solution here.
                    foreach ($this->exface->getContext()->getScopeApplication()->getFilterContext()->getConditions($thisObj) as $cond) {
                        if ($req->getAlias() == $cond->getExpression()->toString() && ($cond->getComparator() == EXF_COMPARATOR_EQUALS || $cond->getComparator() == EXF_COMPARATOR_IS)) {
                            $this->setColumnValues($req->getAlias(), $cond->getValue());
                        }
                    }
                }
            } else {
               $req_col->setValuesFromDefaults();
            }
            
            if ($req_col->hasEmptyValues()) {
                throw new DataSheetMissingRequiredValueError($this, null, null, null, $req_col, $req_col->findEmptyRows());
            }
        }
        
        // Add values to the query and/or create subsheets
        $values_found = false;
        $relatedSheets = [];
        foreach ($this->getColumns() as $column) {
            // Skip columns, that do not represent a meta attribute
            if (! $column->getExpressionObj()->isMetaAttribute()) {
                continue;
            }
            
            // Move related columns to subsheets based on their objects
            // Do it before handling nested sheets as nested sheets with
            // multi-step relations should be moved to subsheets too!
            if (! $column->getAttribute()->getRelationPath()->isEmpty()) {
                // If the column contains nested data, its attribute alias is a relation. So we only need a subsheet
                // if the relation path has more than one relation in it (otherwise it would be regular nested data).
                // Regular related data always goes into a subsheet
                if ($column->getDataType() instanceof DataSheetDataType) {
                    $relPath = $column->getAttribute()->getRelationPath()->getSubpath(0, -1);
                    // If it is regular nested data, put it into the $nestedSheetCols array and skip the rest for
                    // this column.
                    if ($relPath->isEmpty()) {
                        $nestedSheetCols[] = $column;
                        continue;
                    }
                    // If it is nested data to put in a subsheet, make the subsheet be based on the second-last
                    // relation in the path - so that exactly one relation remains.
                    $relSheetAttrAlias = $column->getAttribute()->getRelationPath()->getSubpath(-1)->toString();
                } else {
                    $relPath = $column->getAttribute()->getRelationPath();
                    $relSheetAttrAlias = $column->getAttribute()->getAlias();
                }
                // Do not create a subsheet if it will not have any data - that would only cause errors. This
                // check also allow optional subsheets - no values, no subsheet.
                if ($column->isEmpty(true)) {
                    continue;
                }
                // Now we are ready to create a subsheet and pass data to it
                if (null === $relSheet = $relatedSheets[$relPath->toString()]) {
                    //$relSheet = DataSheetFactory::createFromObject($relPath->getEndObject());
                    $relSheet = DataSheetFactory::createSubsheet($this, $relPath->getEndObject(), $relPath->getRelationLast()->getRightKeyAttribute()->getAlias(), $relPath->getRelationFirst()->getLeftKeyAttribute()->getAlias(), $relPath);
                    $relatedSheets[$relPath->toString()] = $relSheet;
                }
                $relSheet->getColumns($relSheetAttrAlias)->addFromExpression($relSheetAttrAlias)->setValues($column->getValues());
                continue;
            }
            
            // if the column contains nested data sheets, we will need to save them after we
            // created the data for the main sheet - so skip them here.
            if ($column->getDataType() instanceof DataSheetDataType) {
                $nestedSheetCols[] = $column;
                continue;
            } 
            
            // Skip columns with read-only attributes
            if (! $column->getAttribute()->isWritable()) {
                continue;
            }
            
            // If at least one column has values, remember this.
            if ($column->isEmpty() === false) {
                $values_found = true;
            }
            
            // Add all other columns to values
            $query->addValues($column->getExpressionObj()->toString(), $column->getValuesNormalized());
        }
        
        if (! $values_found) {
            throw new DataSheetWriteError($this, 'Cannot create data in data source: no values found to save!');
        }
        
        // If there we want an update for existing UIDs, we need to check if they exist first.
        // This is only possible for readable UIDs, because otherwise we can't check if they exist!
        if ($update_if_uid_found === true && $this->hasUidColumn(true) === true && $this->getUidColumn()->getAttribute()->isReadable() === true) {
            $checkUidsSheet = DataSheetFactory::createFromObject($thisObj);
            $checkUidsSheet->getFilters()->addConditionFromColumnValues($this->getUidColumn());
            $checkUidsCol = $checkUidsSheet->getColumns()->addFromUidAttribute();
            $checkUidsSheet->dataRead();
            if ($checkUidsSheet->isEmpty() === false) {
                throw new DataSheetWriteError($this, 'Cannot create data with UIDs "' . implode($checkUidsCol->getAttribute()->getValueListDelimiter(), $checkUidsCol->getValues()) . '": these UIDs alread exist! Update on duplicate UIDs not supported yet in data sheets!');
            }
        }
        
        // Run the query
        $connection = $thisObj->getDataConnection();
        $transaction->addDataConnection($connection);
        try {
            $result = $query->create($connection);
            $new_uids = [];
            $uidKey = $this->hasUidColumn() ? $this->getUidColumn()->getName() : DataColumn::sanitizeColumnName($thisObj->getUidAttributeAlias());
            foreach ($result->getResultRows() as $row) {
                $new_uids[] = $row[$uidKey];
            }
        } catch (\Throwable $e) {
            $transaction->rollback();
            $commit = false;
            throw new DataSheetWriteError($this, $e->getMessage(), null, $e);
        }
        
        // Save the new UIDs in the data sheet
        if (! empty($new_uids)) {
            $this->setColumnValues($thisObj->getUidAttributeAlias(), $new_uids);
        }
        
        // Handle subsheet with columns with relations
        foreach ($relatedSheets as $relatedSheet) {
            $relatedKeyCol = $relatedSheet->getColumns()->addFromExpression($relatedSheet->getJoinKeyAliasOfSubsheet());
            $thisKeyCol = $relatedSheet->getJoinKeyColumnOfParentSheet();
            foreach ($thisKeyCol->getValues() as $r => $val) {
                $relatedKeyCol->setValue($r, $val);
            }
            $relatedSheet->dataCreate($update_if_uid_found, $transaction);
            // TODO update data in the main sheet with values from the related sheet - only for those columns with
            // corresponding relation path. This would make the main sheet also get default values and values altered
            // be behaviors. See if($create_if_uid_not_found) {...} in dataUpdate() for similar logic. Perhaps both
            // can be combined into a new method. Using joinLeft() does not work as it would add all sorts of system
            // columns of the related sheet too.
        }
        
        // Create data for the nested sheets
        foreach ($nestedSheetCols as $column) {
            $this->dataCreateNestedSheets($column, $transaction, $update_if_uid_found);
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        if ($result->getAllRowsCounter() !== null) {
            $this->setCounterForRowsInDataSource($result->getAllRowsCounter());
        } elseif ($result->hasMoreRows() === false) {
            $this->setCounterForRowsInDataSource($this->countRows());
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnCreateDataEvent($this, $transaction));
        
        return $result->getAffectedRowsCounter();
    }
    
    /**
     * Creates nested data for a column of the main sheet, that contains data sheets as values.
     * 
     * @param DataColumnInterface $column
     * @param bool $updateIfUidFound
     * @param DataTransactionInterface $transaction
     * 
     * @throws InvalidArgumentException
     * @throws DataSheetWriteError
     * 
     * @return int
     */
    protected function dataCreateNestedSheets(DataColumnInterface $column, DataTransactionInterface $transaction, bool $updateIfUidFound) : int
    {
        $thisObj = $this->getMetaObject();
        $counter = 0;
        
        if (! ($column->getDataType() instanceof DataSheetDataType)) {
            throw new InvalidArgumentException('Cannot create nested data for data sheet column "' . $column->getName() . '": invalid column data type "' . $column->getDataType()->getAliasWithNamespace() . '"! Expecting type "exface.Core.DataSheet" or a derivative!');
        }
        
        $nestedRel = $thisObj->getRelation($column->getAttributeAlias());
        $thisSheetKeyAttr = $nestedRel->getLeftKeyAttribute();
        
        // Find foreign keys in the parent data sheet - the UIDs in most cases, but eventually also a custom key column
        if ($thisSheetKeyAttr->isExactly($this->getUidColumn()->getAttribute())) {
            $newKeys = $this->getUidColumn()->getValues(false);
        } else {
            // If the foreign key is not the UID, see if the corresponding column exists. If not, try to read it using
            // the UIDs for filtering. Keep in mind, that additionally read rows may be in another order!
            if ($thisSheetKeyCol = $this->getColumns()->getByAttribute($thisSheetKeyAttr)) {
                $newKeys = $thisSheetKeyCol->getValues(false);
            } elseif ($this->hasUidColumn(true) && $this->getMetaObject()->isReadable()) {
                $keysReadSheet = DataSheetFactory::createFromObject($this->getMetaObject());
                $keysReadSheet->getFilters()->addConditionFromColumnValues($this->getUidColumn());
                $keysReadSheet->getColumns()->addFromUidAttribute();
                $keysReadCol = $keysReadSheet->getColumns()->addFromAttribute($thisSheetKeyAttr);
                $keysReadSheet->dataRead();
                foreach ($this->getUidColumn()->getValues() as $rowNo => $keyUid) {
                    $newKeys[$rowNo] = $keysReadCol->getValueByUid($keyUid);
                }
            } else {
                throw new DataSheetWriteError($this, 'Cannot create nested data: no columns for foreign key "' . $thisSheetKeyAttr->__toString() . '" found and no UID values exist to load the keys from the data source.');
            }
        }
        
        if (count($newKeys) !== count($column->getValues(false))) {
            throw new DataSheetWriteError($this, 'Cannot create nested data: ' . count($column->getValues(false)) . ' nested data sheets found for ' . count($newKeys) . ' foreign keys in the parent sheet.');
        }
        
        foreach ($column->getValues(false) as $rowNr => $sheetArr) {
            if (! $sheetArr) {
                continue;
            }
            
            $nestedSheet = DataSheetFactory::createFromAnything($this->getWorkbench(), $sheetArr);
            
            if ($nestedSheet === null || $nestedSheet->isEmpty(true) === true) {
                continue;
            }
            
            if ($nestedRel->getCardinality()->__toString() !== RelationCardinalityDataType::ONE_TO_N) {
                throw new DataSheetWriteError($this, 'Cannot create nested data for "' . $thisObj->getName() . '" (' . $nestedRel->getRightObject()->getAliasWithNamespace() . ') within "' . $thisObj->getRightObject()->getName() . '": only one-to-many relations allowed!');
            }
            
            $rowKey = $newKeys[$rowNr];
            
            if ($rowKey === null || $rowKey === '') {
                throw new DataSheetWriteError($this, 'Number of created head-rows does not match the number of children rows!', '75TPT5L');
            }
            
            if ($updateIfUidFound === false && $nestedSheet->hasUidColumn() === true) {
                $nestedSheet->getUidColumn()->setValueOnAllRows('');
            }
            
            $nestedFKeyAttr = $nestedRel->getRightKeyAttribute();
            $nestedFKeyCol = $nestedSheet->getColumns()->addFromAttribute($nestedFKeyAttr);
            $nestedFKeyCol->setValueOnAllRows($rowKey);
            $counter += $nestedSheet->dataCreate(false, $transaction);
        }
        
        return $counter;
    }

    /**
     * TODO Ask the user before a cascading delete!
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataDelete()
     */
    public function dataDelete(DataTransactionInterface $transaction = null, bool $cascading = true) : int
    {
        if ($this->getMetaObject()->isWritable() === false) {
            throw new DataSheetWriteError($this, 'Cannot delete data for object ' . $this->getMetaObject()->getAliasWithNamespace() . ': object is not writeable!', '70Y6HAK');
        }
        
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        if ($this->isUnfiltered()) {
            throw new DataSheetWriteError($this, 'Cannot delete all instances of "' . $this->getMetaObject()->getAliasWithNamespace() . '": forbidden operation!', '6T5VCA6');
        }
        
        $affected_rows = 0;
        
        // Fire OnBeforeDeleteDataEvent to allow preprocessing and alternative deletetion logic
        $eventBefore = $this->getWorkbench()->eventManager()->dispatch(new OnBeforeDeleteDataEvent($this, $transaction));
        
        // create new query for the main object
        $query = QueryBuilderFactory::createForObject($this->getMetaObject());
                
        // Add a UID-filter if we know, which UIDs are to be deleted
        // Otherwise just use all filters of the data sheet
        $uidsToDelete = [];
        if (($uidCol = $this->getUidColumn()) && ! $uidCol->isEmpty(true)) {
            if ($uidsToDelete = $uidCol->getValues(false)) {
                $query->addFilterCondition(ConditionFactory::createFromExpression($this->exface, $this->getUidColumn()->getExpressionObj(), implode($this->getUidColumn()->getAttribute()->getValueListDelimiter(), $uidsToDelete), EXF_COMPARATOR_IN));
            }
        } else {
            $query->setFiltersConditionGroup($this->getFilters());
        }
        
        if ($cascading === true && $eventBefore->isPreventDeleteCascade() === false) {
            // Check if there are dependent object, that require cascading deletes
            foreach ($this->getSubsheetsForCascadingDelete() as $ds) {
                try {
                    // Just perform the delete if there really is some data to delete. This sure means an extra data source connection, but
                    // preventing delete operations on empty data sheets also prevents calculating their cascading deletes, etc. This saves
                    // a lot of iterations and reduces the risc of unwanted deletes due to some unforseeable filter constellations.
                    
                    // First check if the sheet theoretically can have data - that is, if it has UIDs in it's rows or at least some filters
                    // This makes sure, reading data in the next step will not return the entire table, which would then get deleted of course!
                    if ((! $ds->hasUidColumn() || $ds->getUidColumn()->isEmpty()) && $ds->getFilters()->isEmpty()) {
                        continue;
                    }
                    
                    // See if the data sheet has any columns and add the system attributes if not
                    // We need some columns as we will read data later on
                    if ($ds->getColumns()->isEmpty()) {
                        $ds->getColumns()->addFromSystemAttributes();
                    }
                    
                    // If the there can be data, but there are no rows, read the data
                    switch (true) {
                        // If there are no columns, delete without reading current data. This still can happen
                        // even after we added system columns a few lines ago - there may not be any system columns
                        // - e.g. for a SQL view which was accidently marked as writable in the metamodel
                        case $ds->getColumns()->isEmpty():
                            $ds->dataDelete($transaction, $cascading);
                            break;
                        // Read current data to double-check there is something to delete
                        case $ds->dataRead() > 0:                    
                            // If the sheet has a filled UID column, better replace all filters
                            // by a simple IN-filter over UIDs. This simplifies the logic in most
                            // query builders a lot! No all data sources can delete filtering over
                            // relations, but most will be able to delete by UID.
                            if ($ds->hasUidColumn(true)) {
                                $dsUidCol = $ds->getUidColumn();
                                // Remove all filters except for those over the UID column
                                foreach ($ds->getFilters()->getConditions(function(ConditionInterface $cond) use ($dsUidCol) {
                                    return $cond->getExpression()->toString() !== $dsUidCol->getAttributeAlias();
                                }) as $cond) {
                                    $ds->getFilters()->removeCondition($cond);
                                }
                                // Add an IN-filter for the UID column
                                $ds->getFilters()->addConditionFromColumnValues($ds->getUidColumn());
                            }
                            $ds->dataDelete($transaction, $cascading);
                            break;
                    }
                } catch (MetaObjectHasNoDataSourceError $e) {
                    // Just ignore objects without data sources - there is nothing to delete there!
                } catch (\Throwable $e) {
                    throw new DataSheetDeleteError($ds, 'Cannot delete related data for ' . $this->getMetaObject()->__toString() . ': ' . rtrim($e->getMessage(), ".!") . '. Please remove related ' . $ds->getMetaObject()->__toString() . ' manually and try again.', '6ZISUAJ', $e);
                }
            }
        }
        
        if ($eventBefore->isPreventDelete() === false) {
            // run the query
            $connection = $this->getMetaObject()->getDataConnection();
            $transaction->addDataConnection($connection);
            try {
                $result = $query->delete($connection);
                $affected_rows += $result->getAffectedRowsCounter();
            } catch (\Throwable $e) {
                $transaction->rollback();
                throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), null, $e);
            }
            
            if ($result->getAllRowsCounter() !== null) {
                $this->setCounterForRowsInDataSource($result->getAllRowsCounter());
            } elseif ($result->hasMoreRows() === false) {
                $this->setCounterForRowsInDataSource(0);
            }
        } else {
            $affected_rows = $this->countRows();
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(new OnDeleteDataEvent($this, $transaction));
        
        return $affected_rows;
    }

    /**
     * Returns an array with data sheets containig all instances, that would need to be deleted cascadingly if the
     * data of this sheet would be deleted.
     *
     * @return DataSheetInterface[]
     */
    public function getSubsheetsForCascadingDelete()
    {
        $subsheets = array();
        // Check if there are dependent objects, that require cascading deletes
        // This is the case, if the deleted object has reverse relations (1-to-many), where the relation is marked
        // with the "Delete with related object" flag.
        $thisObj = $this->getMetaObject();
        /* @var $rel \exface\Core\Interfaces\Model\MetaRelationInterface */
        foreach ($thisObj->getRelations() as $rel) {
            try {
                if ($rel->getCardinality() == RelationCardinalityDataType::N_TO_ONE) {
                    continue;
                }
                if ($rel->getCardinality() == RelationCardinalityDataType::ONE_TO_ONE) {
                    // for 1-to-1 relaitons it is important, for which object the relation was defined.
                    if ($rel->getRightKeyAttribute()->isRelation() === true && $rel->getRightKeyAttribute()->getRelation()->reverse() === $rel) {
                        // If the 1-to-1 relation actually belongs to the right object, we need
                        // to see if that object must be deleted (just like with 1-to-n relations)
                        // TODO #1-to-1-relations
                        continue;
                    } else {
                        continue;
                    }
                }
                // Skip objects, that are not writable
                if ($rel->getRightObject()->isWritable() === false) {
                    continue;
                }
                
                // See if the relation is marked to delete its right object (= related) together with the left object
                if ($rel->isRightObjectToBeDeletedWithLeftObject()) {
                    $ds = DataSheetFactory::createFromObject($rel->getRightObject());
                    // Use all filters of the original query in the cascading queries
                    $ds->setFilters($this->getFilters()->rebase($rel->getAliasWithModifier()));
                    // Additionally add a filter over UIDs in the original query, if it has data with UIDs. This makes sure, the cascading deletes
                    // only affect the loaded rows and nothing "invisible" to the user!
                    if ($thisUidCol = $this->getUidColumn()) {
                        $uids = $thisUidCol->getValues(false);
                        if (! empty($uids)) {
                            // Add a filter of the key attribute of the relation on its right side (e.g. if deleting USERs, we
                            // would also delete USER_ROLE_USERS with a filter over the USER attribute - which is the right key
                            // of the reverse relation from USER to USER_ROLE_USERS)
                            $ds->getFilters()->addConditionFromValueArray($rel->getRightKeyAttribute()->getAlias(), $uids);
                        }
                        
                        // For self-relations some additional filters need to be done on cascading delete sheets!
                        if ($this->getMetaObject()->isExactly($ds->getMetaObject()) === true) {
                            // The cascading delete should not attempt to delete the rows already taken care
                            // of by this sheet - so exclude them by filter! Otherwise we will get infinite 
                            // recursion!
                            $ds->getFilters()->addConditionFromString($thisUidCol->getAttributeAlias(), implode(',', $uids), ComparatorDataType::NOT_IN);
                            // Also keep UID-filters of the main sheet in addition to the rebased filters
                            // to make sure, that if we have excluding filters (= meaning DO NOT DELETE 
                            // certain UIDs), the cascading deletes will not delete the corresponding items
                            // either.
                            // For example: concider nested categories via PARENT attribute. If we delete
                            // all with UID!=2, the cascading deletes might kill category 2 too if it is a child
                            // of any other category being deleted. Adding UID!=2 to the cascading subsheets will 
                            // ensure, that category 2 survives in any case!
                            foreach ($this->getFilters()->getConditions(function(ConditionInterface $cond) use ($thisUidCol) {
                                return $cond->getExpression()->toString() === $thisUidCol->getAttributeAlias();
                            }) as $cond) {
                                $ds->getFilters()->addCondition($cond->copy());
                            }
                        }
                    }
                    $subsheets[] = $ds;
                }
            } catch (\Throwable $e) {
                throw new DataSheetDeleteError($this, 'Cannot read data for cascading delete of ' . $rel->getRightObject()->__toString() . ': ' . $e->getMessage(), null, $e);
            }
        }
        return $subsheets;
    }

    /**
     *
     * @return DataSheetList|DataSheetSubsheet[]
     */
    public function getSubsheets()
    {
        return $this->subsheets;
    }

    /**
     * Array of sorters to apply when reading from the data source
     * 
     * @uxon-property sorters
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataSorter[]
     * @uxon-template [{"attribute_alias": "","direction": "ASC"}]
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getSorters()
     */
    public function getSorters()
    {
        return $this->sorters;
    }

    /**
     * Returns TRUE if the data sheet has at least one sorter and FALSE otherwise
     *
     * @return boolean
     */
    public function hasSorters()
    {
        if (! $this->getSorters()->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getAutoSort()
     */
    public function getAutoSort() : bool
    {
        return $this->autosort;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setAutoSort()
     */
    public function setAutoSort(bool $value) : DataSheetInterface
    {
        $this->autosort = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setCounterForRowsInDataSource()
     */
    public function setCounterForRowsInDataSource(int $count = null) : DataSheetInterface
    {
        $this->total_row_count = $count;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setAutoCount()
     */
    public function setAutoCount(bool $trueOrFalse) : DataSheetInterface
    {
        $this->autocount = $trueOrFalse;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function getAutoCount() : bool
    {
        return $this->autocount;
    }

    /**
     * Returns multiple rows of the data sheet as an array of associative array (e.g.
     * [rownum => [col1 => val1, col2 => val2, ...] ])
     * By default returns all rows. Use the arguments to select only a range of rows.
     *
     * @param number $how_many            
     * @param number $offset            
     * @return array
     */
    function getRows($how_many = 0, $offset = 0)
    {
        $return = array();
        if ($how_many > 0 || $offset > 0) {
            foreach ($this->rows as $nr => $row) {
                if ($nr >= $offset && $how_many < count($return)) {
                    $return[$nr] = $row;
                }
            }
        } else {
            $return = $this->rows;
        }
        return $return;
    }

    /**
     * Returns the specified row as an associative array (e.g.
     * [col1 => val1, col2 => val2, ...])
     *
     * @param number $row_number            
     * @return multitype:
     */
    function getRow($row_number = 0)
    {
        return $this->rows[$row_number];
    }

    /**
     * Returns the first row, that contains a given value in the specified column.
     * Returns NULL if no row matches.
     *
     * @param string $column_name            
     * @param mixed $value            
     * @throws DataSheetColumnNotFoundError
     * @return array
     */
    public function getRowByColumnValue($column_name, $value)
    {
        $column = $this->getColumn($column_name);
        if (! $column) {
            throw new DataSheetColumnNotFoundError($this, 'Cannot find row by column value: invalid column name "' . $column_name . '"!');
        }
        return $this->getRow($column->findRowByValue($value));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getTotalsRows()
     */
    public function getTotalsRows(bool $onlyTotaledCols = true) : array
    {
        if ($onlyTotaledCols === true) {
            return $this->totals_rows;
        } else {
            $cnt = count($this->totals_rows);
            $rows = [];
            for ($i = 0; $i < $cnt; $i++) {
                $rows[$i] = $this->getTotalsRow($i, $onlyTotaledCols);
            }
            return $rows;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getTotalsRow()
     */
    public function getTotalsRow(int $idx = 0, bool $onlyTotaledCols = true) : ?array
    {
        $row = $this->getTotalsRows()[$idx];
        if ($row !== null && $onlyTotaledCols === false) {
            foreach ($this->getColumns() as $col) {
                if (! array_key_exists($col->getName(), $row)) {
                    $row[$col->getName()] = null;
                }
            }
        }
        return $row;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::countRowsInDataSource()
     */
    public function countRowsInDataSource() : ?int
    {
        // FIXME need to check if the sheet is fresh at this point. On the other hand,
        // the sheet must get marked not fresh if filters change as they have direct
        // effect on the number of rows available in the data source.
        if ($this->total_row_count === null && $this->autocount === true && $this->getMetaObject()->isReadable() === true) {
            return $this->dataCount();
        }
        return $this->total_row_count;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getColumns()
     */
    public function getColumns()
    {
        return $this->cols;
    }

    /**
     * Replaces the columns of the sheet with the given list of column definitions 
     * 
     * @uxon-property columns
     * @uxon-type \exface\Core\CommonLogic\DataSheets\DataColumn
     * @uxon-template [{"attribute_alias": ""}]
     * 
     * WARNING: This method replaces the columns of the data sheet without 
     * changing anything in the rows! This may result in rows data, that does
     * not belong to any column!
     * 
     * Use with care! This may cause inconsistensies or unwanted data reads!
     * 
     * TODO This method seams to dangerous. Need to find out, when columns are 
     * actually marked out of date!
     *
     * @param DataColumnList $columns            
     */
    public function setColumns(DataColumnList $columns)
    {
        $columns->setParent($this);
        $this->cols = $columns;
        return $this;
    }

    /**
     * 
     */
    public function removeRowsForColumn($column_name)
    {
        foreach (array_keys($this->getRows()) as $id) {
            unset($this->rows[$id][$column_name]);
            if (empty($this->rows[$id])) {
                $this->removeRow($id);
            }
        }
        return $this;
    }

    /**
     * Returns a data column object by column name.
     * This is an alias for get_columns()->get($name)!
     * FIXME Remove in favor of get_columns()->get($name). This method is just temporarily here as long as the
     * strange bug with the wrong parent sheet is not fixed.
     *
     * @param string $name
     * @return DataColumn
     */
    public function getColumn($name)
    {
        if ($result = $this->getColumns()->get($name)) {
            if ($result->getDataSheet() !== $this) {
                // TODO The next line is a workaround for a strange bug: calling $this->getColumn('X')->setValues() would not update the data sheet and thus the result
                // of calling $this->getColumnValues('X') was different from this->getColumn('X')->getValues(). I have no idea why... This line sure fixes the problem
                // but it needs to be investigated at some point as it might also hit other parent-child-combinations!
                $result->setDataSheet($this);
                throw new DataSheetRuntimeError($this, 'Column "' . $result->getName() . '" belongs to the wrong data sheet!');
            }
            return $result;
        }
        return false;
    }

    /**
     * Returns the data sheet column containing the UID values of the main object or false if the data sheet does not contain that column
     *
     * @return \exface\Core\Interfaces\DataSheets\DataColumnInterface
     */
    public function getUidColumn()
    {
        return $this->getColumns()->get($this->getUidColumnName());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::hasUidColumn()
     */
    public function hasUidColumn(bool $checkValues = false) : bool
    {
        $col = $this->getUidColumn();
        if ($col === null){
            return false;
        }
        
        if ($checkValues === true && $col->isEmpty(true)) {
            return false;
        }
        
        return true;
    }

    /**
     * The main meta object of the sheet
     * 
     * @uxon-property object_alias
     * @uxon-type metamodel:object
     * @uxon-required true
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getMetaObject()
     */
    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getAggregations()
     */
    public function getAggregations()
    {
        return $this->aggregation_columns;
    }

    /**
     * Returns TRUE if the data sheet has at least one aggregator and FALSE otherwise
     *
     * @return boolean
     */
    public function hasAggregations()
    {
        if (! $this->getAggregations()->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the root condition group with all filters of the data sheet
     *
     * @return ConditionGroup
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Condition group to filter the data when reading from the data source.
     * 
     * @uxon-property filters
     * @uxon-type \exface\Core\CommonLogic\Model\ConditionGroup
     * @uxon-template {"operator": "AND","conditions":[{"expression": "","comparator": "=","value": ""}]}
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setFilters()
     */
    public function setFilters(ConditionGroup $condition_group)
    {
        $this->filters = $condition_group;
        return $this;
    }

    /**
     * Returns a JSON representation of the data sheet with all it's data.
     * This JSON can be used to recreate the data
     * sheet later or just to make the data well readable.
     *
     * @return string JSON
     */
    public function toUxon()
    {
        return $this->exportUxonObject()->toJson(true);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $arr = [];
        $arr['object_alias'] = $this->getMetaObject()->getAliasWithNamespace();
        
        $cols = [];
        foreach ($this->getColumns() as $col) {
            $cols[] = $col->exportUxonObject()->toArray();
        }
        if (empty($cols) === false) {
            $arr['columns'] = $cols;
        }
        
        if ($this->isEmpty() === false) {
            $arr['rows'] = $this->getRows();
        }
        
        $arr['totals_rows'] = $this->getTotalsRows();
        $arr['filters'] = $this->getFilters()->exportUxonObject()->toArray();
        $arr['rows_limit'] = $this->getRowsLimit();
        $arr['rows_offset'] = $this->getRowsOffset();
        
        foreach ($this->getSorters() as $sorter) {
            $arr['sorters'][] = $sorter->exportUxonObject()->toArray();
        }
        
        if ($this->getAutoSort() !== true) {
            $arr['auto_sort'] = $this->getAutoSort();
        }
        
        if ($this->getAutoCount() !== true) {
            $arr['auto_count'] = $this->getAutoSort();
        }
        
        foreach ($this->getAggregations() as $aggr) {
            $arr['aggregators'][] = $aggr->exportUxonObject()->toArray();
        }
        
        return new UxonObject($arr);
    }

    public function importUxonObject(UxonObject $uxon)
    {
        
        // Columns
        if ($uxon->hasProperty('columns')) {
            foreach ($uxon->getProperty('columns') as $col) {
                if ($col instanceof UxonObject) {
                    $column = DataColumnFactory::createFromUxon($this, $col);
                    $this->getColumns()->add($column);
                } else {
                    $this->getColumns()->addFromExpression($col);
                }
            }
        }
        
        // Rows
        if ($uxon->hasProperty('rows')) {
            $rows = $uxon->getProperty('rows')->toArray();
            if (! empty($rows)) {
                $this->addRows($rows);
            }
        }
        
        // Totals - ony for backwards compatibilty for times, where the totals functions were
        // defined outside the column definition.
        // IMPORTANT: This must happen AFTER columns and row were created, since totals are added to existing columns!
        if ($uxon->hasProperty('totals_functions')) {
            foreach ($uxon->getProperty('totals_functions') as $column_name => $functions) {
                if (! $column = $this->getColumns()->get($column_name)) {
                    $column = $this->getColumns()->addFromExpression($column_name);
                }
                if ($functions instanceof UxonObject) {
                    foreach ($functions as $func) {
                        $total = DataColumnTotalsFactory::createFromString($column, $func->getProperty('function'));
                        $column->getTotals()->add($total);
                    }
                } else {
                    $total = DataColumnTotalsFactory::createFromString($column, $func->getProperty('function'));
                    $column->getTotals()->add($total);
                }
            }
        }
        
        if ($uxon->hasProperty('filters')) {
            $this->setFilters(ConditionGroupFactory::createFromUxon($this->exface, $uxon->getProperty('filters'), $this->getMetaObject()));
        }
        
        if ($uxon->hasProperty('rows_limit')) {
            $this->setRowsLimit($uxon->getProperty('rows_limit'));
        } elseif ($uxon->hasProperty('rows_on_page')) {
            // Still support old property name rows_on_page
            $this->setRowsLimit($uxon->getProperty('rows_on_page'));
        }
        
        if ($uxon->hasProperty('rows_offset')) {
            $this->setRowsOffset($uxon->getProperty('rows_offset'));
        } elseif ($uxon->hasProperty('row_offset')) {
            // Still support old property name row_offset
            $this->setRowsOffset($uxon->getProperty('row_offset'));
        }
        
        if ($uxon->hasProperty('sorters')) {
            $this->getSorters()->importUxonObject($uxon->getProperty('sorters'));
        }
        
        if ($uxon->hasProperty('aggregators')) {
            $this->getAggregations()->importUxonObject($uxon->getProperty('aggregators'));
        }
        
        if (null !== $val = $uxon->getProperty('auto_sort')) {
            $this->setAutoSort($val);
        }
        
        if (null !== $val = $uxon->getProperty('auto_count')) {
            $this->setAutoCount($val);
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRows()
     */
    public function removeRows(array $rowIndexes = null)
    {
        if ($rowIndexes !== null) {
            $rowIndexes = array_unique($rowIndexes);
            rsort($rowIndexes);
            foreach ($rowIndexes as $i) {
                $this->removeRow($i);
            }
        } else {
            $this->rows = array();
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRow()
     */
    public function removeRow(int $row_number) : DataSheetInterface
    {
        unset($this->rows[$row_number]);
        $this->rows = array_values($this->rows);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRowsByUid()
     */
    public function removeRowsByUid($uid)
    {
        // Do nothing if there is no UID column
        if (! $this->getUidColumn()) {
            return $this;
        }
        
        // Find all rows matching the UID and remove them starting with the
        // row with the highest number. This is important as removing a row
        // will reindex the $this->rows, changing the indexes of subsequent
        // rows. Removing higher row numbers first will leave lower row indexes
        // untouched, so the initially calculated numbers array will remain
        // valid.
        $rowNumbers = $this->getUidColumn()->findRowsByValue($uid);
        rsort($rowNumbers);
        foreach ($rowNumbers as $row_number) {
            $this->removeRow($row_number);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isEmpty()
     */
    public function isEmpty(bool $checkValues = false) : bool
    {
        if (empty($this->rows)) {
            return true;
        } elseif ($checkValues === false) {
            return false;
        }
        
        foreach ($this->getColumns() as $col) {
            if (! $col->isEmpty(true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isBlank()
     */
    public function isBlank() : bool
    {
        return ($this->isUnfiltered() === true && $this->isEmpty() === true);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isUnsorted()
     */
    public function isUnsorted() : bool
    {
        return $this->getSorters()->isEmpty() ? true : false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isPaged()
     */
    public function isPaged() : bool
    {
        return $this->getRowsLimit() > 0 && $this->dataSourceHasMoreRows === true;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setFresh()
     */
    public function setFresh(bool $value) : DataSheetInterface
    {
        foreach ($this->getColumns() as $col) {
            $col->setFresh($value);
        }
        $this->is_fresh = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isFresh()
     */
    public function isFresh() : bool
    {
        foreach ($this->getColumns() as $col) {
            if ($col->isFresh() === false) {
                return false;
            }
        }
        return $this->is_fresh;
    }

    public function getRowsLimit() : ?int
    {
        return $this->rows_on_page;
    }

    /**
     * Max. number of rows to read (all if not set explicitly)
     * 
     * @uxon-property rows_limit
     * @uxon-type int|null
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setRowsLimit()
     */
    public function setRowsLimit($value) : DataSheetInterface
    {
        $this->rows_on_page = $value;
        return $this;
    }

    public function getRowsOffset() : int
    {
        return $this->row_offset;
    }

    /**
     * Number of rows to skip when reading.
     * 
     * @uxon-property rows_offset
     * @uxon-type integer
     * 
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setRowsOffset()
     */
    public function setRowsOffset(int $value) : DataSheetInterface
    {
        $this->row_offset = $value;
        return $this;
    }

    /**
     * Merges the current data sheet with another one.
     * Values of the other sheet will overwrite values of identical columns of the current one!
     *
     * @param DataSheet $other_sheet            
     * @return DataSheet
     */
    public function merge(DataSheetInterface $other_sheet, bool $overwriteValues = true)
    {
        // Ignore empty other sheets
        if ($other_sheet->isEmpty() && $other_sheet->getFilters()->isEmpty()) {
            return $this;
        }
        // Check if both sheets are identical
        if ($this === $other_sheet) {
            return $this;
        }
        
        // Check if the sheets are based on the same object
        if ($this->getMetaObject()->getId() !== $other_sheet->getMetaObject()->getId()) {
            throw new DataSheetMergeError($this, 'Cannot merge non-empty data sheets for different objects ("' . $this->getMetaObject()->getAliasWithNamespace() . '" and "' . $other_sheet->getMetaObject()->getAliasWithNamespace() . '"): not implemented!', '6T5E8GM');
        }
        
        // Check if both sheets have UID columns if they are not empty
        if ((! $this->isEmpty() && ! $this->getUidColumn()) || (! $other_sheet->isEmpty() && ! $other_sheet->getUidColumn())) {
            if ($this->countRows() == $other_sheet->countRows()) {
                $this->joinLeft($other_sheet);
            } else {
                throw new DataSheetMergeError($this, 'Cannot merge data sheets without UID columns!', '6T5E8Q6');
            }
        }
        
        // TODO Merge filters too! Pay attention to the fact, that filters will be stored in the filter context,
        // so if this action is called again right away, they will come from different sources. It is important no
        // to dublicate them!
        
        // Merge columns
        $joinKey = $this->getMetaObject()->getUidAttributeAlias();
        if ($overwriteValues) {
            $this->joinLeft($other_sheet, $joinKey, $joinKey);
        } else {
            $baseSheet = $other_sheet->copy();
            $baseSheet->joinLeft($this, $joinKey, $joinKey);
            $this->removeRows()->addRows($baseSheet->getRows());
        }
        
        return $this;
    }

    public function getMetaObjectRelationPath(MetaObjectInterface $related_object)
    {
        // TODO First try to determine the path by searching for the related object among columns, filters, sorters, etc.
        // It is verly likely, that the user is interested in exactly the one relation already used! This is expecially important for
        // reverse relations, which can be ambiguous.
        return $this->getMetaObject()->findRelationPath($related_object);
    }

    /**
     * Clones the data sheet and returns the new copy.
     * The copy will point to the same meta object, but will
     * have it's own columns, filters, aggregations, etc.
     *
     * @return DataSheetInterface
     */
    public function copy() : self
    {
        $copy = DataSheetFactory::createFromUxon($this->getWorkbench(), $this->exportUxonObject());
        // Copy internal properties, that do not get exported to UXON
        foreach ($this->getColumns() as $key => $col) {
            if ($col->getIgnoreFixedValues()) {
                $copy->getColumns()->get($key)->setIgnoreFixedValues($col->getIgnoreFixedValues());
            }
        }
        
        $copy->setAutoCount($this->getAutoCount());
        if ($this->total_row_count !== null) {
            $copy->setCounterForRowsInDataSource($this->countRowsInDataSource());
        }
        
        return $copy;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getUidColumnName()
     */
    public function getUidColumnName()
    {
        if (! $this->uid_column_name) {
            $this->uid_column_name = $this->getMetaObject()->getUidAttributeAlias();
        }
        return $this->uid_column_name;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setUidColumnName()
     */
    public function setUidColumnName($value)
    {
        $this->uid_column_name = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setUidColumn()
     */
    public function setUidColumn(DataColumnInterface $column)
    {
        $this->uid_column_name = $column->getName();
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataValidate()
     */
    public function dataValidate() : bool
    {
        $this->getWorkbench()->eventManager()->dispatch(new OnBeforeValidateDataEvent($this));
        if ($this->invalid_data_flag !== true) {
            // TODO Add data type validation here
            $this->invalid_data_flag = false;
        }
        $this->getWorkbench()->eventManager()->dispatch(new OnValidateDataEvent($this));
        return $this->invalid_data_flag;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataCount()
     */
    public function dataCount() : ?int
    {
        try {
            $query = $this->dataReadInitQueryBuilder($this->getMetaObject());
        } catch (DataSheetReadError $dsre) {
            throw $dsre;
        } catch (\Throwable $e) {
            throw new DataSheetReadError($this, 'Cannot initialize query builder for object ' . $this->getMetaObject()->__toString() . ': ' . $e->getMessage(), null, $e);
        }
        
        try {
            $result = $query->count($this->getMetaObject()->getDataConnection());
        } catch (\Throwable $e) {
            throw new DataSheetReadError($this, $e->getMessage(), null, $e);
        }
        
        $this->setCounterForRowsInDataSource($result->getAllRowsCounter());
        return $result->getAllRowsCounter();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataMarkInvalid()
     */
    public function dataMarkInvalid()
    {
        $this->invalid_data_flag = true;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isUnfiltered()
     */
    public function isUnfiltered() : bool
    {
        if ((! $this->getUidColumn() || $this->getUidColumn()->isEmpty()) && $this->getFilters()->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::hasColumTotals()
     */
    public function hasColumTotals()
    {
        foreach ($this->getColumns() as $col){
            if ($col->hasTotals()){
                return true;
            }
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::extract()
     */
    public function extract(ConditionalExpressionInterface $conditionOrGroup, bool $readMissingData = false) : DataSheetInterface
    {
        $condGrp = $conditionOrGroup->toConditionGroup();
        
        if ($readMissingData === true) {
            foreach ($condGrp->getConditionsRecursive() as $cond) {
                foreach ($cond->getExpression()->getRequiredAttributes() as $attrAlias) {
                    if (! $this->getColumns()->getByExpression($attrAlias)) {
                        $missingCols[] = $attrAlias;
                    }
                }
            }
            if (! empty($missingCols)) {
                if ($this->hasUidColumn(true)) {
                    $missingSheet = DataSheetFactory::createFromObject($this->getMetaObject());
                    $missingSheet->getColumns()->addFromUidAttribute();
                    foreach ($missingCols as $alias) {
                        $missingSheet->getColumns()->addFromExpression($alias);
                    }
                    $missingSheet->getFilters()->addConditionFromColumnValues($this->getUidColumn());
                    $missingSheet->dataRead();
                    $checkSheet = $this->copy();
                    $checkSheet->joinLeft($missingSheet, $checkSheet->getUidColumnName(), $missingSheet->getUidColumnName());
                } else {
                    throw new DataSheetExtractError($this, 'Cannot filter data rows: information required for conditions is not available in the data sheet!', null, null, $condGrp);
                }
            } else {
                $checkSheet = $this;
            }
        } else {
            $checkSheet = $this;
        }
        
        $extractedRows = [];
        foreach ($this->getRows() as $rowNr => $row) {
            if ($condGrp->evaluate($checkSheet, $rowNr) === true) {
                $extractedRows[] = $row;
            }
        }
        
        return $this->copy()->removeRows()->addRows($extractedRows);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::sort()
     */
    public function sort(DataSorterListInterface $sorters = null, bool $normalizeValues = true) : DataSheetInterface
    {
        if ($this->isEmpty()) {
            return $this;
        }
        
        if ($sorters === null) {
            $sorters = $this->getSorters();
            if ($sorters->isEmpty() && $this->getAutoSort() === true) {
                $sorters = $this->getMetaObject()->getDefaultSorters();
            }
        }
        
        $sorter = new RowDataArraySorter();
        foreach ($sorters as $s) {
            $col = $this->getColumns()->getByAttribute($s->getAttribute());
            if ($col === false) {
                throw new DataSheetStructureError($this, 'Cannot sort data sheet via ' . $s->getAttributeAlias() . ': no corresponding column found!');
            }
            // Values like numbers and dates can only be sorted reliably if they are normalized!
            if ($normalizeValues === true) {
                $col->normalizeValues();
            }
            $sorter->addCriteria($col->getName(), $s->getDirection());
        }
        $this->rows = $sorter->sort($this->getRows());
        return $this;
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::hasAggregateAll()
     */
    public function hasAggregateAll() : bool
    {
        if ($this->aggregateAll === null) {
            if ($this->hasAggregations() === true) {
                return false;
            }
            
            if ($this->getColumns()->isEmpty()) {
                return false;
            }
            
            foreach ($this->getColumns() as $col) {
                if ($col->hasAggregator() === false && ! $col->isCalculated()) {
                    return false;
                }
            }
            
            return true;
        }
        return $this->aggregateAll;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getRowsDecrypted()
     */
    public function getRowsDecrypted($how_many = 0, $offset = 0, string $secret = null) : array
    {
        $encryptedRows = $this->getRows($how_many, $offset);
        if (empty($encryptedRows)) {
            return $encryptedRows;
        }
        $secret = $secret ?? EncryptedDataType::getSecret($this->getWorkbench());
        $rows = array_slice($encryptedRows, 0);
        $columns = $this->getColumns();
        foreach ($rows as $idx => $row) {                       
            foreach ($columns as $col) {
                $datatype = $col->getDataType();
                if ($datatype instanceof EncryptedDataType) {
                    $colName = $col->getName();
                    $encrypted = $row[$colName];
                    if ($datatype->isValueEncrypted($encrypted)) {
                        $decrypted = EncryptedDataType::decrypt($secret, $encrypted, $datatype->getEncryptionPrefix());
                        $row[$colName] = $decrypted;
                    }
                }
            }
            $rows[$idx] = $row;
        }
        return $rows;
    }
    
    
    public function findRowsByValues(array $rowData) : array
    {
        $result = [];
        foreach ($this->getRows() as $idx => $row) {
            foreach ($rowData as $fld => $val) {
                if ($row[$fld] !== $val) {
                    continue 2;
                }
            }
            $result[] = $idx;
        }
        return $result;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget, string $tabCaption = 'Data Sheet')
    {
        // Add a tab with the data sheet UXON
        $uxon_tab = $debug_widget->createTab();
        $uxon_tab->setCaption($tabCaption);
        $uxon_widget = WidgetFactory::createFromUxonInParent($uxon_tab, new UxonObject([
            'widget_type' => 'InputUxon',
            'caption' => PhpClassDataType::findClassNameWithoutNamespace(get_class($this)),
            'hide_caption' => true,
            'width' => '100%',
            'height' => '100%',
            'disabled' => true,
            'root_prototype' => '\\' . DataSheet::class,
            'root_object' => $this->getMetaObject()->getAliasWithNamespace(),
            'value' => $this->getCensoredDataSheet()->exportUxonObject()->toJson(true)
        ]));
        $uxon_tab->addWidget($uxon_widget);
        $debug_widget->addTab($uxon_tab);
        return $debug_widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getCensoredDataSheet()
     */
    public function getCensoredDataSheet() : DataSheetInterface
    {
        $dataSheet = $this->copy();
        foreach ($dataSheet->getColumns() as $col) {
            if ($col->getDataType()->isSensitiveData() === true) {
                for ($i = 0; $i < $dataSheet->countRows(); $i++) {
                    $col->setValue($i, 'CENSORED');
                }
            }
        }
        return $dataSheet;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getRowsDiff()
     */
    public function getRowsDiff(DataSheetInterface $otherSheet, array $exclude = []) : array
    {
        $diffRows = [];
        $diffIdxs = [];
        
        $excludeColumns = [];
        foreach ($exclude as $excl) {
            switch (true) {
                case $excl instanceof MetaAttributeInterface:
                    if ($col = $this->getColumns()->getByAttribute($excl)) {
                        $excludeColumns[] = $col;
                    }
                    break;
                case $excl instanceof DataColumnInterface:
                    $excludeColumns[] = $excl;
                    break;
                default:
                    if ($col = $this->getColumns()->getByExpression($excl)) {
                        $excludeColumns[] = $col;
                    }
                    break;                
            }
        }
        
        foreach ($this->getColumns() as $thisCol) {
            if (in_array($thisCol, $excludeColumns)) {
                continue;
            }
            if ($otherCol = $otherSheet->getColumns()->get($thisCol->getName())) {
                $diffIdxs = array_merge($diffIdxs, array_keys($thisCol->diffRows($otherCol)));
            } else {
                $diffIdxs = array_merge($diffIdxs, array_keys($thisCol->getValues(false)));
            }
        }
        $diffIdxs = array_unique($diffIdxs);
        sort($diffIdxs);
        foreach ($diffIdxs as $i) {
            $diffRows[$i] = $this->getRow($i);
        }
        return $diffRows;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRowDuplicates()
     */
    public function removeRowDuplicates() : array
    {
        $rowHashes = [];
        $removeRows = [];
        if ($this->hasUidColumn() && count(array_unique($this->getUidColumn()->getValues(false))) === $this->countRows()) {
            return $removeRows;
        }
        foreach ($this->getRows() as $i => $row) {
            $rowHash = json_encode($row);
            if (in_array($rowHash, $rowHashes)) {
                $removeRows[$i] = $row;
            } else {
                $rowHashes[$i] = $rowHash;
            }
        }
        if (! empty($removeRows)) {
            foreach (array_reverse(array_keys($removeRows)) as $i) {
                $this->removeRow($i);
            }
        }
        return $removeRows;
    }
}