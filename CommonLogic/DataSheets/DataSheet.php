<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\ConditionGroup;
use exface\Core\CommonLogic\Model\Condition;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\DataSheets\DataSheetMergeError;
use exface\Core\Factories\QueryBuilderFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Factories\ConditionFactory;
use exface\Core\Factories\ConditionGroupFactory;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataSheetSubsheetFactory;
use exface\Core\Factories\DataColumnTotalsFactory;
use exface\Core\Interfaces\DataSheets\DataAggregatorListInterface;
use exface\Core\Interfaces\DataSheets\DataSorterListInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\Factories\EventFactory;
use exface\Core\Events\DataSheetEvent;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\DataSheets\DataSheetJoinError;
use exface\Core\Exceptions\DataSheets\DataSheetImportRowError;
use exface\Core\Exceptions\DataSheets\DataSheetUidColumnNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetWriteError;
use exface\Core\Exceptions\DataSheets\DataSheetColumnNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetRuntimeError;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;
use exface\Core\Exceptions\DataSheets\DataSheetReadError;
use exface\Core\Exceptions\DataSheets\DataSheetMissingRequiredValueError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\CommonLogic\Model\Relation;

/**
 * Internal data respresentation object in exface.
 * Similar to an Excel-table:
 *   |Column1|Column2|Column3|
 * 1 | value | value | value | \
 * 2 | value | value | value | > data rows: each one is an array(column=>value)
 * 3 | value | value | value | /
 * 4 | total | total | total | \
 * 5 | total | total | total | / total rows: each one is an array(column=>value)
 *
 * The data sheet dispatches the following events prefixed by the main objects alias (@see DataSheetEvent):
 * - UpdateData (.Before/.After)
 * - ReplaceData (.Before/.After)
 * - CreateData (.Before/.After)
 * - DeleteData (.Before/.After)
 * - ValidateData (.Before/.After)
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

    private $total_row_count = 0;

    private $subsheets = array();

    private $aggregators = array();

    private $rows_on_page = NULL;

    private $row_offset = 0;

    private $uid_column_name = null;

    private $invalid_data_flag = false;

    // properties NOT to be copied on copy()
    private $exface;

    private $meta_object;

    public function __construct(\exface\Core\CommonLogic\Model\Object $meta_object)
    {
        $this->exface = $meta_object->getModel()->getWorkbench();
        $this->meta_object = $meta_object;
        $this->filters = ConditionGroupFactory::createEmpty($this->exface, EXF_LOGICAL_AND);
        $this->cols = new DataColumnList($this->exface, $this);
        $this->aggregators = new DataAggregatorList($this->exface, $this);
        $this->sorters = new DataSorterList($this->exface, $this);
        // IDEA Can we use the generic EntityListFactory here or do we need a dedicated factory for subsheet lists?
        $this->subsheets = new DataSheetList($this->exface, $this);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::addRows($rows)
     */
    public function addRows(array $rows, $merge_uid_dublicates = false)
    {
        foreach ($rows as $row) {
            $this->addRow((array) $row, $merge_uid_dublicates);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::addRow()
     */
    public function addRow(array $row, $merge_uid_dublicates = false)
    {
        if (count($row) > 0) {
            if ($merge_uid_dublicates && $this->getUidColumn() && $uid = $row[$this->getUidColumn()->getName()]) {
                $uid_row_nr = $this->getUidColumn()->findRowByValue($uid);
                if ($uid_row_nr !== false) {
                    $this->rows[$uid_row_nr] = array_merge($this->rows[$uid_row_nr], $row);
                } else {
                    $this->rows[] = $row;
                }
            } else {
                $this->rows[] = $row;
            }
            // ensure, that all columns used in the rows are present in the data sheet
            $this->getColumns()->addMultiple(array_keys((array) $row));
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
    public function joinLeft(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $left_key_column = null, $right_key_column = null, $relation_path = '')
    {
        // First copy the columns of the right data sheet ot the left one
        $right_cols = array();
        foreach ($data_sheet->getColumns() as $col) {
            $right_cols[] = $col->copy();
        }
        $this->getColumns()->addMultiple($right_cols, $relation_path);
        // Now process the data and join rows
        if (! is_null($left_key_column) && ! is_null($right_key_column)) {
            foreach ($this->rows as $left_row => $row) {
                if (! $data_sheet->getColumns()->get($right_key_column)) {
                    throw new DataSheetMergeError($this, 'Cannot find right key column "' . $right_key_column . '" for a left join!', '6T5E849');
                }
                $right_row = $data_sheet->getColumns()->get($right_key_column)->findRowByValue($row[$left_key_column]);
                if ($right_row !== false) {
                    foreach ($data_sheet->getColumns() as $col) {
                        $this->setCellValue(RelationPath::relationPathAdd($relation_path, $col->getName()), $left_row, $data_sheet->getCellValue($col->getName(), $right_row));
                    }
                }
            }
        } elseif (is_null($left_key_column) && is_null($right_key_column)) {
            foreach ($this->rows as $left_row => $row) {
                $this->rows[$left_row] = array_merge($row, $data_sheet->getRow($left_row));
            }
        } else {
            throw new DataSheetJoinError($this, 'Cannot join data sheets, if only one key column specified!', '6T5V0GU');
        }
        return true;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::importRows()
     */
    public function importRows(DataSheetInterface $other_sheet)
    {
        if (! $this->getMetaObject()->isExactly($other_sheet->getMetaObject()->getAliasWithNamespace())) {
            throw new DataSheetImportRowError($this, 'Cannot replace rows for object "' . $this->getMetaObject()->getAliasWithNamespace() . '" with rows from "' . $other_sheet->getMetaObject()->getAliasWithNamespace() . '": replacing rows only possible for identical objects!', '6T5V1DR');
        }
        
        if (! $this->getUidColumn() && $other_sheet->getUidColumn()) {
            $uid_column = $other_sheet->getUidColumn()->copy();
            $this->getColumns()->add($uid_column);
        }
        
        $columns_with_formulas = array();
        foreach ($this->getColumns() as $this_col) {
            if ($this_col->getFormula()) {
                $columns_with_formulas[] = $this_col->getName();
                continue;
            }
            if ($other_col = $other_sheet->getColumn($this_col->getName())) {
                if (count($this_col->getValues(false)) > 0 && count($this_col->getValues(false)) !== count($other_col->getValues(false))) {
                    throw new DataSheetImportRowError('Cannot replace rows of column "' . $this_col->getName() . '": source and target columns have different amount of rows!', '6T5V1XX');
                }
                $this_col->setValues($other_col->getValues(false));
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
    public function getColumnValues($column_name, $include_totals = false)
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
    public function setColumnValues($column_name, $column_values, $totals_values = null)
    {
        // If the column is not yet there, add it, but make it hidden
        if (! $this->getColumn($column_name)) {
            $this->getColumns()->addFromExpression($column_name, null, true);
        }
        
        if (is_array($column_values)) {
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
    public function getCellValue($column_name, $row_number)
    {
        $data_row_cnt = $this->countRowsLoaded();
        if ($row_number >= $data_row_cnt) {
            return $this->totals_rows[$row_number - $data_row_cnt][$column_name];
        }
        return $this->rows[$row_number][$column_name];
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::setCellValue()
     */
    public function setCellValue($column_name, $row_number, $value)
    {
        // Create the column, if not already there
        if (! $this->getColumn($column_name)) {
            $this->getColumns()->addFromExpression($column_name);
        }
        
        // Detect, if the cell belongs to a total row
        $data_row_cnt = $this->countRowsLoaded();
        if ($row_number >= $data_row_cnt && $row_number < $this->countRowsLoaded(true)) {
            $this->totals_rows[$row_number - $data_row_cnt][$column_name] = $value;
        }
        
        // Set the cell valu in the data matrix
        $this->rows[$row_number][$column_name] = $value;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getTotalValue()
     */
    public function getTotalValue($column_name, $row_number)
    {
        return $this->totals_rows[$row_number][$column_name];
    }

    /**
     *
     * @param DataColumnInterface $col            
     * @param \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder $query            
     */
    protected function getDataForColumn(DataColumnInterface $col, \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder $query)
    {
        // add the required attributes
        foreach ($col->getExpressionObj()->getRequiredAttributes() as $attr) {
            try {
                $attribute = $this->getMetaObject()->getAttribute($attr);
            } catch (MetaAttributeNotFoundError $e) {
                continue;
            }
            // if the attributes data source is the same, as the one of the main object, add the attribute to the query
            if ($attribute->getObject()->getDataSourceId() == $this->getMetaObject()->getDataSourceId()) {
                // if a formula is applied to the attribute, get all attributes required for the formula
                // if it is just a plain attribute, add it and nothing else
                if ($expr = $attribute->getFormula()) {
                    if ($expr->isFormula()) {
                        $expr = $attribute->get_data_expression();
                        $expr->setRelationPath($attribute->getRelationPath()->toString());
                        $this->getColumns()->addFromExpression($expr, $attr);
                        $this->getDataForColumn($this->getColumn($attr), $query);
                    }
                } else {
                    $query->addAttribute($attr);
                }
            } else {
                // If the attribute belongs to a related object with a different data source than the main object, make a subsheet and ultimately a separate query.
                // To create a subsheet we need to split the relation path to the current attribute into the part leading to the foreign key in the main data source
                // and the part in the next data source. We always split into two parts by the first data source border: if there are more data sources involved,
                // the subsheet will take care of splitting the rest of the path. Here is an example: Concider comparing turnover between the point-of-sale system and
                // the backend ERP. Each system shall have stores and their turnover in different data bases: TURNOVER<-POS->FLOOR->POS_STORE<->ERP_STORE->TURNOVER. One way
                // would be creating a data sheet for the POS object with one of the columns being FLOOR->POS_STORE->ERP_STORE->TURNOVER. This relation path will need
                // to be split into FLOOR->POS_STORE->FOREIGN_KEY_TO_ERP_STORE and ERP_STORE->TURNOVER. The first path will make sure, the main sheet will have a key
                // to join the subsheet afterwards, while the second part will become on of the subsheet columns.
                
                // TODO This piece of code is really hard to read. It should be a separate method.
                // IDEA This will probably not work, if the relation path returns to some attribute of the initial data source. Is it possible at all?!
                $rel_path_in_main_ds = '';
                $rel_path_in_subsheet = '';
                $rel_path_to_subsheet = '';
                $last_rel_path = '';
                $rels = RelationPath::relationPathParse($attribute->getAliasWithRelationPath());
                foreach ($rels as $depth => $rel) {
                    $rel_path = RelationPath::relationPathAdd($last_rel_path, $rel);
                    if ($this->getMetaObject()->getRelatedObject($rel_path)->getDataSourceId() == $this->getMetaObject()->getDataSourceId()) {
                        $rel_path_in_main_ds = $last_rel_path;
                    } else {
                        if (! $rel_path_to_subsheet) {
                            // Remember the path to the relation to the object with the other data source
                            $rel_path_to_subsheet = $rel_path;
                        } else {
                            // All path parts following the one to the other data source, go into the subsheet
                            $rel_path_in_subsheet = RelationPath::relationPathAdd($rel_path_in_subsheet, $rel);
                        }
                    }
                    $last_rel_path = $rel_path;
                    if ($depth == (count($rels) - 2))
                        break; // stop one path step before the end because that would be the attribute of the related object
                }
                // Create a subsheet for the relation if not yet existent and add the required attribute
                if (! $subsheet = $this->getSubsheets()->get($rel_path_to_subsheet)) {
                    $subsheet_object = $this->getMetaObject()->getRelatedObject($rel_path_to_subsheet);
                    $subsheet = DataSheetSubsheetFactory::createForObject($subsheet_object, $this);
                    $this->getSubsheets()->add($subsheet, $rel_path_to_subsheet);
                    if (! $this->getMetaObject()->getRelation($rel_path_to_subsheet)->isReverseRelation()) {
                        // add the foreign key to the main query and to this sheet
                        $query->addAttribute($rel_path_to_subsheet);
                        // IDEA do we need to add the column to the sheet? This is just useless data...
                        // Additionally it would make trouble when the column has formatters...
                        
                        $this->getColumns()->addFromExpression($rel_path_to_subsheet, '', true);
                    }
                }
                // Add the current attribute to the subsheet prefixing it with it's relation path relative to the subsheet's object
                $subsheet->getColumns()->addFromExpression(RelationPath::relationPathAdd($rel_path_in_subsheet, $attribute->getAlias()));
                // Add the related object key alias of the relation to the subsheet to that subsheet. This will be the right key in the future JOIN.
                if ($rel_path_to_subsheet_right_key = $this->getMetaObject()->getRelation($rel_path_to_subsheet)->getRelatedObjectKeyAlias()) {
                    $subsheet->getColumns()->addFromExpression(RelationPath::relationPathAdd($rel_path_in_main_ds, $rel_path_to_subsheet_right_key));
                } else {
                    throw new DataSheetUidColumnNotFoundError($this, 'Cannot find UID (primary key) for subsheet: no key alias can be determined for the relation "' . $rel_path_to_subsheet . '" from "' . $this->getMetaObject()->getAliasWithNamespace() . '" to "' . $this->getMetaObject()->getRelation($rel_path_to_subsheet)->getRelatedObject()->getAliasWithNamespace() . '"!');
                }
            }
            
            if ($attribute->getFormatter()) {
                $col->setFormatter($attribute->getFormatter());
                $col->getFormatter()->setRelationPath($attribute->getRelationPath()->toString());
                if ($aggregator = DataAggregator::getAggregateFunctionFromAlias($col->getExpressionObj()->toString())) {
                    $col->getFormatter()->mapAttribute(str_replace(':' . $aggregator, '', $col->getExpressionObj()->toString()), $col->getExpressionObj()->toString());
                }
                foreach ($col->getFormatter()->getRequiredAttributes() as $req) {
                    if (! $this->getColumn($req)) {
                        $column = $this->getColumns()->addFromExpression($req, '', true);
                        $this->getDataForColumn($column, $query);
                    }
                }
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataRead()
     */
    public function dataRead($limit = null, $offset = null)
    {
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ReadData.Before'));
        
        // Empty the data before reading
        // IDEA Enable incremental reading by distinguishing between reading the same page an reading a new page
        $this->removeRows();
        
        if (is_null($limit))
            $limit = $this->getRowsOnPage();
        if (is_null($offset))
            $offset = $this->getRowOffset();
        
        // create new query for the main object
        $query = QueryBuilderFactory::createFromAlias($this->exface, $this->getMetaObject()->getQueryBuilder());
        $query->setMainObject($this->getMetaObject());
        
        foreach ($this->getColumns() as $col) {
            $this->getDataForColumn($col, $query);
            foreach ($col->getTotals()->getAll() as $row => $total) {
                $query->addTotal($col->getAttributeAlias(), $total->getFunction(), $row);
            }
        }
        
        // Ensure, the columns with system attributes are always in the select
        // FIXME With growing numbers of behaviors and system attributes, this becomes a pain, as more and more possibly
        // aggregated columns are added automatically - even if the sheet is only meant for reading. Maybe we should let
        // the code creating the sheet add the system columns. The behaviors will prduce errors if this does not happen anyway.
        foreach ($this->getMetaObject()->getAttributes()->getSystem()->getAll() as $attr) {
            if (! $this->getColumns()->getByAttribute($attr)) {
                // Check if the system attribute has a default aggregator if the data sheet is being aggregated
                if ($this->hasAggregators() && $attr->getDefaultAggregateFunction()) {
                    $col = $this->getColumns()->addFromExpression($attr->getAlias() . DataAggregator::AGGREGATION_SEPARATOR . $attr->getDefaultAggregateFunction());
                } else {
                    $col = $this->getColumns()->addFromAttribute($attr);
                }
                $this->getDataForColumn($col, $query);
            }
        }
        
        // Set explicitly defined filters
        $query->setFiltersConditionGroup($this->getFilters());
        // Add filters from the contexts
        foreach ($this->exface->context()->getScopeApplication()->getFilterContext()->getConditions($this->getMetaObject()) as $cond) {
            $query->addFilterCondition($cond);
        }
        
        // set aggregations
        foreach ($this->getAggregators() as $aggr) {
            $query->addAggregation($aggr->getAttributeAlias());
        }
        
        // set sorting
        $sorters = $this->hasSorters() ? $this->getSorters() : $this->getMetaObject()->getDefaultSorters();
        foreach ($sorters as $sorter) {
            $query->addSorter($sorter->getAttributeAlias(), $sorter->getDirection());
        }
        
        if ($limit > 0) {
            $query->setLimit($limit, $offset);
        }
        
        try {
            $result = $query->read($this->getMetaObject()->getDataConnection());
        } catch (\Throwable $e) {
            throw new DataSheetReadError($this, $e->getMessage(), ($e instanceof ExceptionInterface ? $e->getAlias() : null), $e);
        }
        
        $this->addRows($query->getResultRows());
        $this->totals_rows = $query->getResultTotals();
        $this->total_row_count = $query->getResultTotalRows();
        
        // load data for subsheets if needed
        if ($this->countRows()) {
            foreach ($this->getSubsheets() as $rel_path => $subsheet) {
                if (! $this->getMetaObject()->getRelation($rel_path)->isReverseRelation()) {
                    $foreign_keys = $this->getColumnValues($rel_path);
                    $subsheet->addFilterFromString($this->getMetaObject()->getRelation($rel_path)->getRelatedObjectKeyAlias(), implode($this->getMetaObject()->getRelation($rel_path)->getRelatedObjectKeyAttribute()->getValueListDelimiter(), array_unique($foreign_keys)), EXF_COMPARATOR_IN);
                    $left_key_column = $rel_path;
                    $right_key_column = $this->getMetaObject()->getRelation($rel_path)->getRelatedObjectKeyAlias();
                } else {
                    if ($this->getMetaObject()->getRelation($rel_path)->getMainObjectKeyAttribute()) {
                        throw new DataSheetJoinError($this, 'Joining subsheets via reverse relations with explicitly specified foreign keys, not implemented yet!', '6T5V36I');
                    } else {
                        $foreign_keys = $this->getUidColumn()->getValues();
                        $subsheet->addFilterFromString($this->getMetaObject()->getRelation($rel_path)->getForeignKeyAlias(), implode($this->getMetaObject()->getRelation($rel_path)->getForeignKeyAttribute()->getValueListDelimiter(), array_unique($foreign_keys)), EXF_COMPARATOR_IN);
                        // FIXME Fix Reverse relations key bug. Getting the left key column from the reversed relation here is a crude hack, but
                        // the get_main_object_key_alias() strangely does not work for reverse relations
                        $left_key_column = $this->getMetaObject()->getRelation($rel_path)->getReversedRelation()->getRelatedObjectKeyAlias();
                        $right_key_column = $this->getMetaObject()->getRelation($rel_path)->getForeignKeyAlias();
                    }
                }
                $subsheet->dataRead();
                // add the columns from the sub-sheets, but prefix their names with the relation alias, because they came from this relation!
                $this->joinLeft($subsheet, $left_key_column, $right_key_column, $rel_path);
            }
        }
        
        // FIXME This foreach calculates the expressions in all columns, which is not a good idea, because most columns are simple attributes
        // and already have their values. However, if the column name has special characters like ":", the column name is not the same, as the
        // the attribute alias, that is the key in the rows. So, this foreach here actually doubles all columns with special characters: e.g. copying
        // row values with the key SOME_ATTRIBUTE:SUM to a key SOME_ATTRIBUTE_SUM. This leads to useless increase of memory consumption, but I'm
        // not sure, how to fix this.
        foreach ($this->getColumns() as $name => $col) {
            $vals = $col->getExpressionObj()->evaluate($this, $name);
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
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ReadData.After'));
        return $result;
    }

    public function countRows()
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
        return $this->dataUpdate(true, $transaction);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataUpdate()
     */
    public function dataUpdate($create_if_uid_not_found = false, DataTransactionInterface $transaction = null)
    {
        $counter = 0;
        
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        // Check if the data source already contains rows with matching UIDs
        // TODO do not update rows, that were created here. Currently they are created and immediately updated afterwards.
        if ($create_if_uid_not_found) {
            if ($this->getUidColumn()) {
                // Create another data sheet selecting those UIDs currently present in the data source
                $uid_check_ds = DataSheetFactory::createFromObject($this->getMetaObject());
                $uid_column = $this->getUidColumn()->copy();
                $uid_check_ds->getColumns()->add($uid_column);
                $uid_check_ds->addFilterFromColumnValues($this->getUidColumn());
                $uid_check_ds->dataRead();
                $missing_uids = $this->getUidColumn()->diffValues($uid_check_ds->getUidColumn());
                if (count($missing_uids) > 0) {
                    $create_ds = $this->copy()->removeRows();
                    foreach ($missing_uids as $missing_uid) {
                        $create_ds->addRow($this->getRowByColumnValue($this->getUidColumn()->getName(), $missing_uid));
                    }
                    $counter += $create_ds->dataCreate(false, $transaction);
                }
            } else {
                throw new DataSheetWriteError($this, 'Creating rows from an update statement without a UID-column not supported yet!', '6T5VBHF');
            }
        }
        
        // After all preparation is done, check to see if there are any rows to update left
        if ($this->isEmpty()) {
            return 0;
        }
        
        // Now the actual updating starts
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'UpdateData.Before'));
        
        // Add columns with fixed values to the data sheet
        $processed_relations = array();
        foreach ($this->getColumns() as $col) {
            if (! $col->getAttribute()) {
                // throw new MetaAttributeNotFoundError($this->getMetaObject(), 'Cannot find attribute for data sheet column "' . $col->getName() . '"!');
                continue;
            }
            // Fetch all attributes with fixed values and add them to the sheet if not already there
            $rel_path = $col->getAttribute()->getRelationPath()->toString();
            if ($processed_relations[$rel_path])
                continue;
            /* @var $attr \exface\Core\CommonLogic\Model\Attribute */
            foreach ($col->getAttribute()->getObject()->getAttributes() as $attr) {
                if ($expr = $attr->getFixedValue()) {
                    $alias_with_relation_path = RelationPath::relationPathAdd($rel_path, $attr->getAlias());
                    if (! $col = $this->getColumn($alias_with_relation_path)) {
                        $col = $this->getColumns()->addFromExpression($alias_with_relation_path, NULL, true);
                    } elseif ($col->getIgnoreFixedValues()) {
                        continue;
                    }
                    $col->setValuesByExpression($expr);
                }
            }
            $processed_relations[$rel_path] = true;
        }
        
        // Create a query
        $query = QueryBuilderFactory::createFromAlias($this->exface, $this->getMetaObject()->getQueryBuilder());
        $query->setMainObject($this->getMetaObject());
        // Add filters to the query
        $query->setFiltersConditionGroup($this->getFilters());
        
        // Add values
        // At this point, it is important to understand, that there are different types of update data sheets possible:
        // - A "regular" sheet with one row per object identified by the UID column. In this case, that object needs to be updated by values from
        // the corresponding columns
        // - A data sheet with a single row and no UID column, where the values of that row should be saved to all object matching the filter
        // - A data sheet with a single row and a UID column, where the one row references multiple object explicitly selected by the user (the UID
        // column will have one cell with a list of UIDs in this case.
        foreach ($this->getColumns() as $column) {
            // Skip columns, that do not represent a meta attribute
            if (! $column->getExpressionObj()->isMetaAttribute()) {
                continue;
            } elseif (! $column->getAttribute()) {
                // Skip columns, that reference non existing attributes
                // TODO Is throwing an exception appropriate here?
                throw new MetaAttributeNotFoundError($this->getMetaObject(), 'Attribute "' . $column->getExpressionObj()->toString() . '" of object "' . $this->getMetaObject()->getAliasWithNamespace() . '" not found!');
            } elseif (DataAggregator::getAggregateFunctionFromAlias($column->getExpressionObj()->toString())) {
                // Skip columns with aggregate functions
                continue;
            }
            
            // Use the UID column as a filter to make sure, only these rows are affected
            if ($column->getAttribute()->getAliasWithRelationPath() == $this->getMetaObject()->getUidAlias()) {
                $query->addFilterFromString($this->getMetaObject()->getUidAlias(), implode($this->getMetaObject()->getUidAttribute()->getValueListDelimiter(), array_unique($column->getValues(false))), EXF_COMPARATOR_IN);
            } else {
                // Add all other columns to values
                
                // First check, if the attribute belongs to a related object
                if ($rel_path = $column->getAttribute()->getRelationPath()->toString()) {
                    if ($this->getMetaObject()->getRelation($rel_path)->isForwardRelation()) {
                        $uid_column_alias = $rel_path;
                    } else {
                        // $uid_column = $this->getColumn($this->getMetaObject()->getRelation($rel_path)->getMainObjectKeyAttribute()->getAliasWithRelationPath());
                        throw new DataSheetWriteError($this, 'Updating attributes from reverse relations ("' . $column->getExpressionObj()->toString() . '") is not supported yet!', '6T5V4HW');
                    }
                } else {
                    $uid_column_alias = $this->getMetaObject()->getUidAlias();
                }
                
                // If it is a direct attribute, add it to the query
                if ($this->getUidColumn()) {
                    // If the data sheet has separate values per row (identified by the UID column), add all the values to the query.
                    // In this case, each object will get its own value. However, we need to ensure, that there are UIDs for each value,
                    // even if the value belongs to a related object. If there is no appropriate UID column for updated related object,
                    // the UID values must be fetched from the data source using an identical data sheet, but having only the required uid column.
                    // Since the new data sheet is cloned, it will have exactly the same filters, order, etc. so we can be sure to fetch only those
                    // UIDs, that should have been in the original sheet. Additionally we need to add a filter over the values of the original UID
                    // column, in case the user had explicitly selected some of the rows of the original data set.
                    if (! $uid_column = $this->getColumn($uid_column_alias)) {
                        $uid_data_sheet = $this->copy();
                        $uid_data_sheet->getColumns()->removeAll();
                        $uid_data_sheet->getColumns()->addFromExpression($this->getMetaObject()->getUidAlias());
                        $uid_data_sheet->getColumns()->addFromExpression($uid_column_alias);
                        $uid_data_sheet->addFilterFromString($this->getMetaObject()->getUidAlias(), implode($this->getUidColumn()->getValues(), $this->getUidColumn()->getAttribute()->getValueListDelimiter()), EXF_COMPARATOR_IN);
                        $uid_data_sheet->dataRead();
                        $uid_column = $uid_data_sheet->getColumn($uid_column_alias);
                    }
                    $query->addValues($column->getExpressionObj()->toString(), $column->getValues(false), $uid_column->getValues(false));
                } else {
                    // If there is only one value for the entire data sheet (no UIDs gived), add it to the query as a single column value.
                    // In this case all object matching the filter will get updated by this value
                    $query->addValue($column->getExpressionObj()->toString(), $column->getValues(false)[0]);
                }
            }
        }
        
        // Run the query
        $connection = $this->getMetaObject()->getDataConnection();
        $transaction->addDataConnection($connection);
        try {
            $counter += $query->update($connection);
        } catch (\Throwable $e) {
            $transaction->rollback();
            $commit = false;
            throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), ($e instanceof ExceptionInterface ? $e->getAlias() : null), $e);
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'UpdateData.After'));
        
        return $counter;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataReplaceByFilters()
     */
    public function dataReplaceByFilters(DataTransactionInterface $transaction = null, $delete_redundant_rows = true, $update_by_uid_ignoring_filters = true)
    {
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ReplaceData.Before'));
        
        $counter = 0;
        if ($delete_redundant_rows) {
            if ($this->getFilters()->isEmpty()) {
                throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data if no filter are defined! This would delete ALL data for the object "' . $this->getMetaObject()->getAliasWithNamespace() . '"!', '6T5V4TS');
            }
            if ($this->getUidColumn()) {
                $redundant_rows_ds = $this->copy();
                $redundant_rows_ds->getColumns()->removeAll();
                $uid_column = $this->getUidColumn()->copy();
                $redundant_rows_ds->getColumns()->add($uid_column);
                $redundant_rows_ds->dataRead();
                $redundant_rows = $redundant_rows_ds->getUidColumn()->diffValues($this->getUidColumn());
                if (count($redundant_rows) > 0) {
                    $delete_ds = DataSheetFactory::createFromObject($this->getMetaObject());
                    $delete_col = $uid_column->copy();
                    $delete_ds->getColumns()->add($delete_col);
                    $delete_ds->getUidColumn()->removeRows()->setValues(array_values($redundant_rows));
                    $counter += $delete_ds->dataDelete($transaction);
                }
            } else {
                throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data for "' . $this->getMetaObject()->getAliasWithNamespace() . '" if no UID column is present in the data sheet', '6T5V5EB');
            }
        }
        
        // If we need to update records by UID and we have a non-empty UID column, we need to remove all filters to make sure the update
        // runs via UID only. Thus, the update is being performed on a copy of the sheet, which does not have any filters. In all other
        // cases, the update should be performed on the original data sheet itself.
        if ($update_by_uid_ignoring_filters && $this->getUidColumn() && ! $this->getUidColumn()->isEmpty()) {
            $update_ds = $this->copy();
            $update_ds->getFilters()->removeAll();
        } else {
            $update_ds = $this;
        }
        
        $counter += $update_ds->dataUpdate(true, $transaction);
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ReplaceData.After'));
        
        return $counter;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataCreate()
     */
    public function dataCreate($update_if_uid_found = true, DataTransactionInterface $transaction = null)
    {
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'CreateData.Before'));
        // Create a query
        $query = QueryBuilderFactory::createFromAlias($this->exface, $this->getMetaObject()->getQueryBuilder());
        $query->setMainObject($this->getMetaObject());
        
        // Add values for columns based on attributes with defaults or fixed values
        foreach ($this->getMetaObject()->getAttributes()->getAll() as $attr) {
            if ($def = ($attr->getDefaultValue() ? $attr->getDefaultValue() : $attr->getFixedValue())) {
                if (! $col = $this->getColumns()->getByAttribute($attr)) {
                    $col = $this->getColumns()->addFromExpression($attr->getAlias());
                }
                $col->setValuesFromDefaults();
            }
        }
        
        // Check, if all required attributes are present
        foreach ($this->getMetaObject()->getAttributes()->getRequired() as $req) {
            if (! $req_col = $this->getColumns()->getByAttribute($req)) {
                // If there is no column for the required attribute, add one
                $col = $this->getColumns()->addFromExpression($req->getAlias());
                // First see if there are default values for this column
                if ($def = ($req->getDefaultValue() ? $req->getDefaultValue() : $req->getFixedValue())) {
                    $col->setValuesByExpression($def);
                } else {
                    // Try to get the value from the current filter contexts: if the missing attribute was used as a direct filter, we assume, that the data is saved
                    // in the same context, so we can set the attribute value to the filter value
                    // TODO Look in other context scopes, not only in the application scope. Still looking for an elegant solution here.
                    foreach ($this->exface->context()->getScopeApplication()->getFilterContext()->getConditions($this->getMetaObject()) as $cond) {
                        if ($req->getAlias() == $cond->getExpression()->toString() && ($cond->getComparator() == EXF_COMPARATOR_EQUALS || $cond->getComparator() == EXF_COMPARATOR_IS)) {
                            $this->setColumnValues($req->getAlias(), $cond->getValue());
                        }
                    }
                }
            } else {
                try {
                    $req_col->setValuesFromDefaults();
                } catch (DataSheetRuntimeError $e) {
                    throw new DataSheetMissingRequiredValueError($this, 'Required attribute "' . $req->getName() . '" (alias "' . $req->getAlias() . '") not set in at least one row!', null, $e);
                }
            }
        }
        
        // Add values
        $values_found = false;
        foreach ($this->getColumns() as $column) {
            // Skip columns, that do not represent a meta attribute
            if (! $column->getExpressionObj()->isMetaAttribute())
                continue;
            // Check if the meta attribute really exists
            if (! $column->getAttribute()) {
                throw new MetaAttributeNotFoundError($this->getMetaObject(), 'Cannot find attribute for data sheet column "' . $column->getName() . '"!');
                continue;
            }
            
            // Check the uid column for values. If there, it's an update!
            if ($column->getAttribute()->getAlias() == $this->getMetaObject()->getUidAlias() && $update_if_uid_found) {
                // TODO
            } else {
                // If at least one column has values, remember this.
                if (count($column->getValues(false)) > 0) {
                    $values_found = true;
                }
                // Add all other columns to values
                $query->addValues($column->getExpressionObj()->toString(), $column->getValues(false));
            }
        }
        
        if (! $values_found) {
            throw new DataSheetWriteError($this, 'Cannot create data in data source: no values found to save!');
        }
        
        // Run the query
        $connection = $this->getMetaObject()->getDataConnection();
        $transaction->addDataConnection($connection);
        try {
            $new_uids = $query->create($connection);
        } catch (\Throwable $e) {
            $transaction->rollback();
            $commit = false;
            throw new DataSheetWriteError($this, $e->getMessage(), ($e instanceof ExceptionInterface ? $e->getAlias() : null), $e);
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        // Save the new UIDs in the data sheet
        $this->setColumnValues($this->getMetaObject()->getUidAlias(), $new_uids);
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'CreateData.After'));
        
        return count($new_uids);
    }

    /**
     * TODO Ask the user before a cascading delete!
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::dataDelete()
     */
    public function dataDelete(DataTransactionInterface $transaction = null)
    {
        // Start a new transaction, if not given
        if (! $transaction) {
            $transaction = $this->getWorkbench()->data()->startTransaction();
            $commit = true;
        } else {
            $commit = false;
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'DeleteData.Before'));
        
        $affected_rows = 0;
        // create new query for the main object
        $query = QueryBuilderFactory::createFromAlias($this->exface, $this->getMetaObject()->getQueryBuilder());
        $query->setMainObject($this->getMetaObject());
        
        if ($this->isUnfiltered()) {
            throw new DataSheetWriteError($this, 'Cannot delete all instances of "' . $this->getMetaObject()->getAliasWithNamespace() . '": forbidden operation!', '6T5VCA6');
        }
        // set filters
        $query->setFiltersConditionGroup($this->getFilters());
        if ($this->getUidColumn()) {
            if ($uids = $this->getUidColumn()->getValues(false)) {
                $query->addFilterCondition(ConditionFactory::createFromExpression($this->exface, $this->getUidColumn()->getExpressionObj(), implode($this->getUidColumn()->getAttribute()->getValueListDelimiter(), $uids), EXF_COMPARATOR_IN));
            }
        }
        
        // Check if there are dependent object, that require cascading deletes
        foreach ($this->getSubsheetsForCascadingDelete() as $ds) {
            // Just perform the delete if there really is some data to delete. This sure means an extra data source connection, but
            // preventing delete operations on empty data sheets also prevents calculating their cascading deletes, etc. This saves
            // a lot of iterations and reduces the risc of unwanted deletes due to some unforseeable filter constellations.
            
            // First check if the sheet theoretically can have data - that is, if it has UIDs in it's rows or at least some filters
            // This makes sure, reading data in the next step will not return the entire table, which would then get deleted of course!
            if ((! $ds->getUidColumn() || $ds->getUidColumn()->isEmpty()) && $ds->getFilters()->isEmpty())
                continue;
            // If the there can be data, but there are no rows, read the data
            if ($ds->dataRead()) {
                $ds->dataDelete($transaction);
            }
        }
        
        // run the query
        $connection = $this->getMetaObject()->getDataConnection();
        $transaction->addDataConnection($connection);
        try {
            $affected_rows += $query->delete($connection);
        } catch (\Throwable $e) {
            $transaction->rollback();
            throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), ($e instanceof ExceptionInterface ? $e->getAlias() : null), $e);
        }
        
        if ($commit && ! $transaction->isRolledBack()) {
            $transaction->commit();
        }
        
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'DeleteData.After'));
        
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
        // This is the case, if the deleted object has reverse relations (1-to-many), where the relation is a mandatory
        // attribute of the related object (that is, if the related object cannot exist without the one we are deleting)
        /* @var $rel \exface\Core\CommonLogic\Model\Relation */
        foreach ($this->getMetaObject()->getRelations(Relation::RELATION_TYPE_REVERSE) as $rel) {
            // FIXME use $rel->getRelatedObjectKeyAttribute() here instead. This must be fixed first though, as it returns false now
            if (! $rel->getRelatedObject()->getAttribute($rel->getForeignKeyAlias())->isRequired()) {
                // FIXME Throw a warning here! Need to be able to show warning along with success messages!
                // throw new DataSheetWriteError($this, 'Cascading deletion via optional relations not yet implemented: no instances were deleted for relation "' . $rel->getAlias() . '" to object "' . $rel->getRelatedObject()->getAliasWithNamespace() . '"!');
            } else {
                $ds = DataSheetFactory::createFromObject($rel->getRelatedObject());
                // Use all filters of the original query in the cascading queries
                $ds->setFilters($this->getFilters()->rebase($rel->getAlias()));
                // Additionally add a filter over UIDs in the original query, if it has data with UIDs. This makes sure, the cascading deletes
                // only affect the loaded rows and nothing "invisible" to the user!
                if ($this->getUidColumn()) {
                    $uids = $this->getUidColumn()->getValues(false);
                    if (count($uids) > 0) {
                        $ds->addFilterInFromString($this->getUidColumn()->getExpressionObj()->rebase($rel->getAlias())->toString(), $uids);
                    }
                }
                $subsheets[] = $ds;
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
     * Creates a new condition and adds it to the filters of this data sheet to the root condition group.
     * FIXME Make ConditionGroup::addConditionsFromString() better usable by introducing the base object there. Then
     * remove this method here.
     *
     * @param string $column_name            
     * @param ambiguos $value            
     * @param string $comparator            
     */
    function addFilterFromString($expression_string, $value, $comparator = null)
    {
        $base_object = $this->getMetaObject();
        $this->getFilters()->addConditionsFromString($base_object, $expression_string, $value, $comparator);
        return $this;
    }

    /**
     * Adds an filter based on a list of values: the column value must equal one of the values in the list.
     * The list may be an array or a comma separated string
     * FIXME move to ConditionGroup, so it can be used for nested groups too!
     *
     * @param string $column            
     * @param string|array $values            
     */
    function addFilterInFromString($column, $value_list)
    {
        if (is_array($value_list)) {
            if ($this->getColumn($column) && $this->getColumn($column)->getAttribute()){
                $delimiter = $this->getColumn($column)->getAttribute()->getValueListDelimiter();
            } else {
                $delimiter = EXF_LIST_SEPARATOR;
            }
            $value = implode($delimiter, $value_list);
        } else {
            $value = $value_list;
        }
        $this->addFilterFromString($column, $value, EXF_COMPARATOR_IN);
    }

    /**
     * Adds an filter based on a list of values: the column value must equal one of the values in the list.
     * The list may be an array or a comma separated string
     * FIXME move to ConditionGroup, so it can be used for nested groups too!
     *
     * @param string $column            
     * @param string|array $values            
     */
    function addFilterIsFromString($column, $value_list)
    {
        if (is_array($value_list)) {
            if ($this->getColumn($column) && $this->getColumn($column)->getAttribute()){
                $delimiter = $this->getColumn($column)->getAttribute()->getValueListDelimiter();
            } else {
                $delimiter = EXF_LIST_SEPARATOR;
            }
            $value = implode($delimiter, $value_list);
        } else {
            $value = $value_list;
        }
        $this->addFilterFromString($column, $value, EXF_COMPARATOR_IN);
    }

    /**
     * Returns an array of data sorters
     *
     * @return DataSorterListInterface
     */
    function getSorters()
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
        if ($this->getSorters()->count() > 0) {
            return true;
        } else {
            return false;
        }
    }

    function setCounterRowsAll($count)
    {
        $this->total_row_count = intval($count);
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
        if ($how_many || $offset) {
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
     * Returns the total rows as assotiative arrays.
     * Multiple total rows can be used to display multiple totals per column.
     *
     * @return array [ column_id => total value ]
     */
    function getTotalsRows()
    {
        return $this->totals_rows;
    }

    function countRowsAll()
    {
        return $this->total_row_count;
    }

    function countRowsLoaded($include_totals = false)
    {
        $cnt = count($this->rows) + ($include_totals ? count($this->totals_rows) : 0);
        return $cnt;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::getColumns()
     */
    public function getColumns()
    {
        return $this->cols;
    }

    /**
     * Replaces the columns of this data sheet with the given column list not changing anything in the rows!
     * Use with care! This may cause inconsistensies or unwanted data reads!
     * TODO This method seams to dangerous.
     * Need to find out, when columns are actually marked out of date!
     *
     * @param DataColumnList $columns            
     */
    public function setColumns(DataColumnList $columns)
    {
        $columns->setParent($this);
        $this->cols = $columns;
        return $this;
    }

    public function removeRowsForColumn($column_name)
    {
        foreach (array_keys($this->getRows()) as $id) {
            unset($this->rows[$id][$column_name]);
            if (count($this->rows[$id]) == 0) {
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
     * @param
     *            string column name
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
    public function hasUidColumn()
    {
        if (is_null($this->getUidColumn())){
            return false;
        }
        
        return true;
    }

    public function getMetaObject()
    {
        return $this->meta_object;
    }

    /**
     *
     * @return DataAggregatorListInterface
     */
    public function getAggregators()
    {
        return $this->aggregators;
    }

    /**
     * Returns TRUE if the data sheet has at least one aggregator and FALSE otherwise
     *
     * @return boolean
     */
    public function hasAggregators()
    {
        if ($this->getAggregators()->count() > 0) {
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

    public function setFilters(ConditionGroup $condition_group)
    {
        $this->filters = $condition_group;
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
        $output = new UxonObject();
        $output->object_alias = $this->getMetaObject()->getAliasWithNamespace();
        
        foreach ($this->getColumns() as $col) {
            $output->columns[] = $col->exportUxonObject();
        }
        
        if (! $this->isEmpty()) {
            $output->rows = $this->getRows();
        }
        
        $output->totals_rows = $this->getTotalsRows();
        $output->filters = $this->getFilters()->exportUxonObject();
        $output->rows_on_page = $this->getRowsOnPage();
        $output->row_offset = $this->getRowOffset();
        if ($this->hasSorters()) {
            foreach ($this->getSorters() as $sorter) {
                $output->sorters[] = $sorter->exportUxonObject();
            }
        }
        if ($this->hasAggregators()) {
            foreach ($this->getAggregators() as $aggr) {
                $output->aggregators[] = $aggr->exportUxonObject();
            }
        }
        return $output;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        
        // Columns
        if (is_array($uxon->getProperty('columns'))) {
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
        if ($rows = $uxon->getProperty('rows')) {
            if (is_array($rows) || ($rows instanceof UxonObject && ! $rows->isEmpty())) {
                $this->addRows((array) $rows);
            }
        }
        
        // Totals - ony for backwards compatibilty for times, where the totals functions were
        // defined outside the column definition.
        // IMPORTANT: This must happen AFTER columns and row were created, since totals are added to existing columns!
        if (is_array($uxon->getProperty('totals_functions')) || $uxon->getProperty('totals_functions') instanceof \stdClass) {
            foreach ((array) $uxon->getProperty('totals_functions') as $column_name => $functions) {
                if (! $column = $this->getColumns()->get($column_name)) {
                    $column = $this->getColumns()->addFromExpression($column_name);
                }
                if (is_array($functions)) {
                    foreach ($functions as $func) {
                        $total = DataColumnTotalsFactory::createFromString($column, $func->function);
                        $column->getTotals()->add($total);
                    }
                } else {
                    $total = DataColumnTotalsFactory::createFromString($column, $func->function);
                    $column->getTotals()->add($total);
                }
            }
        }
        
        if ($uxon->hasProperty('filters')) {
            $this->setFilters(ConditionGroupFactory::createFromObjectOrArray($this->exface, $uxon->getProperty('filters')));
        }
        
        if ($uxon->hasProperty('rows_on_page')) {
            $this->setRowsOnPage($uxon->getProperty('rows_on_page'));
        }
        
        if ($uxon->hasProperty('row_offset')) {
            $this->setRowOffset($uxon->getProperty('row_offset'));
        }
        
        if (is_array($uxon->getProperty('sorters'))) {
            $this->getSorters()->importUxonArray($uxon->getProperty('sorters'));
        }
        if (is_array($uxon->getProperty('aggregators'))) {
            $this->getAggregators()->importUxonArray($uxon->getProperty('aggregators'));
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRows()
     */
    public function removeRows()
    {
        $this->rows = array();
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::removeRow()
     */
    public function removeRow($row_number)
    {
        unset($this->rows[$row_number]);
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
        foreach ($this->getUidColumn()->findRowsByValue($uid) as $row_number) {
            $this->removeRow($row_number);
        }
        return $this;
    }

    public function addFilterFromColumnValues(DataColumnInterface $column)
    {
        $this->addFilterFromString($column->getExpressionObj()->toString(), implode(($column->getAttribute() ? $column->getAttribute()->getValueListDelimiter() : EXF_LIST_SEPARATOR), array_unique($column->getValues(false))), EXF_COMPARATOR_IN);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::isEmpty()
     */
    public function isEmpty()
    {
        if (count($this->getRows()) < 1) {
            return true;
        } else {
            return false;
        }
    }

    public function isBlank()
    {
        if ($this->isUnfiltered() && $this->isEmpty()) {
            return true;
        }
        return false;
    }

    public function isUnsorted()
    {
        return $this->getSorters()->isEmpty() ? true : false;
    }

    protected function setFresh($value)
    {
        foreach ($this->getColumns() as $col) {
            $col->setFresh($value);
        }
        return $this;
    }

    public function isFresh()
    {
        foreach ($this->getColumns() as $col) {
            if ($col->isFresh() === false) {
                return false;
            }
        }
        return true;
    }

    public function getRowsOnPage()
    {
        return $this->rows_on_page;
    }

    public function setRowsOnPage($value)
    {
        $this->rows_on_page = $value;
        return $this;
    }

    public function getRowOffset()
    {
        return $this->row_offset;
    }

    public function setRowOffset($value)
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
    public function merge(DataSheetInterface $other_sheet)
    {
        // Ignore empty other sheets
        if ($other_sheet->isEmpty() && $other_sheet->getFilters()->isEmpty()) {
            return $this;
        }
        // Chek if both sheets are identical
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
        $this->joinLeft($other_sheet, $this->getMetaObject()->getUidAlias(), $this->getMetaObject()->getUidAlias());
        
        return $this;
    }

    public function getMetaObjectRelationPath(Object $related_object)
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
    public function copy()
    {
        $exface = $this->getWorkbench();
        $copy = DataSheetFactory::createFromUxon($exface, $this->exportUxonObject());
        // Copy internal properties, that do not get exported to UXON
        foreach ($this->getColumns() as $key => $col) {
            if ($col->getIgnoreFixedValues()) {
                $copy->getColumns()->get($key)->setIgnoreFixedValues($col->getIgnoreFixedValues());
            }
        }
        return $copy;
    }

    /**
     *
     * @return exface
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
            $this->uid_column_name = $this->getMetaObject()->getUidAlias();
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
    public function dataValidate()
    {
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ValidateData.Before'));
        if ($this->invalid_data_flag !== true) {
            // TODO Add data type validation here
            $this->invalid_data_flag = false;
        }
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataSheetEvent($this, 'ValidateData.After'));
        return $this->invalid_data_flag;
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
    public function isUnfiltered()
    {
        if ((! $this->getUidColumn() || $this->getUidColumn()->isEmpty()) && $this->getFilters()->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }
}

?>