<?php namespace exface\Core\CommonLogic\DataSheets;

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

/**
 * Internal data respresentation object in exface. Similar to an Excel-table:
 *   |Column1|Column2|Column3|
 * 1 | value | value | value | \ 
 * 2 | value | value | value |  > data rows: each one is an array(column=>value)
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
class DataSheet implements DataSheetInterface {
	
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
	
	public function __construct(\exface\Core\CommonLogic\Model\Object $meta_object){
		$this->exface = $meta_object->get_model()->get_workbench();
		$this->meta_object = $meta_object;
		$this->filters = ConditionGroupFactory::create_empty($this->exface, EXF_LOGICAL_AND);
		$this->cols = new DataColumnList($this->exface, $this);
		$this->aggregators = new DataAggregatorList($this->exface, $this);
		$this->sorters = new DataSorterList($this->exface, $this);
		// IDEA Can we use the generic EntityListFactory here or do we need a dedicated factory for subsheet lists?
		$this->subsheets = new DataSheetList($this->exface, $this);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::add_rows($rows)
	 */
	public function add_rows(array $rows){
		foreach ($rows as $row){
			$this->add_row((array)$row);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::add_row()
	 */
	public function add_row(array $row){
		if (count($row) > 0){
			$this->rows[] = $row;
			// ensure, that all columns used in the rows are present in the data sheet
			$this->get_columns()->add_multiple(array_keys((array)$row));
			$this->set_fresh(true);
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::join_left()
	 */
	public function join_left(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $left_key_column = null, $right_key_column = null, $relation_path = ''){
		// First copy the columns of the right data sheet ot the left one
		$right_cols = array();
		foreach ($data_sheet->get_columns() as $col){
			$right_cols[] = $col->copy();
		}
		$this->get_columns()->add_multiple($right_cols, $relation_path);
		// Now process the data and join rows
		if (!is_null($left_key_column) && !is_null($right_key_column)){
			foreach ($this->rows as $left_row => $row){
				if (!$data_sheet->get_columns()->get($right_key_column)){
					throw new DataSheetMergeError($this, 'Cannot find right key column "' . $right_key_column . '" for a left join!', '6T5E849');
				}
				$right_row = $data_sheet->get_columns()->get($right_key_column)->find_row_by_value($row[$left_key_column]);
				if ($right_row !== false){
					foreach ($data_sheet->get_columns() as $col){
						$this->set_cell_value(RelationPath::relation_path_add($relation_path, $col->get_name()), $left_row, $data_sheet->get_cell_value($col->get_name(), $right_row));
					}
				}
			}
		} elseif (is_null($left_key_column) && is_null($right_key_column)){
			foreach ($this->rows as $left_row => $row){
				$this->rows[$left_row] = array_merge($row, $data_sheet->get_row($left_row));
			}
		} else {
			throw new DataSheetJoinError($this, 'Cannot join data sheets, if only one key column specified!', '6T5V0GU');
		}
		return true;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::import_rows()
	 */
	public function import_rows(DataSheetInterface $other_sheet){
		if (!$this->get_meta_object()->is_exactly($other_sheet->get_meta_object()->get_alias_with_namespace())){
			throw new DataSheetImportRowError($this, 'Cannot replace rows for object "' . $this->get_meta_object()->get_alias_with_namespace() . '" with rows from "' . $other_sheet->get_meta_object()->get_alias_with_namespace() . '": replacing rows only possible for identical objects!', '6T5V1DR');
		}
		
		if (!$this->get_uid_column() && $other_sheet->get_uid_column()){
			$uid_column = $other_sheet->get_uid_column()->copy();
			$this->get_columns()->add($uid_column);
		}
		
		$columns_with_formulas = array();
		foreach ($this->get_columns() as $this_col){
			if ($this_col->get_formula()){
				$columns_with_formulas[] = $this_col->get_name();
				continue;
			}
			if ($other_col = $other_sheet->get_column($this_col->get_name())){
				if (count($this_col->get_values(false)) > 0 && count($this_col->get_values(false)) !== count($other_col->get_values(false))){
					throw new DataSheetImportRowError('Cannot replace rows of column "' . $this_col->get_name() . '": source and target columns have different amount of rows!', '6T5V1XX');
				}
				$this_col->set_values($other_col->get_values(false));
			}
		}
		
		foreach ($columns_with_formulas as $name){
			$this->get_column($name)->set_values_by_expression($this->get_column($name)->get_formula());
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::get_column_values()
	 */
	public function get_column_values($column_name, $include_totals=false){
		$col = array();
		$rows = $include_totals ? $this->rows + $this->totals_rows : $this->rows;
		foreach ($rows as $row){
			$col[] = $row[$column_name];
		}
		return $col;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::set_column_values()
	 */
	public function set_column_values($column_name, $column_values, $totals_values = null){
		// If the column is not yet there, add it, but make it hidden
		if (!$this->get_column($column_name)){
			$this->get_columns()->add_from_expression($column_name, null, true);
		}
		
		if (is_array($column_values)){
			// first update data rows
			foreach ($column_values as $row => $val){
				$this->rows[$row][$column_name] = $val;
			}
		} else {
			foreach ($this->rows as $nr => $row){
				$this->rows[$nr][$column_name] = $column_values;
			}
		}

		// if totals given, update the columns totals
		if ($totals_values){
			foreach ($this->totals_rows as $nt => $row){
				$this->totals_rows[$nt][$column_name] = (is_array($totals_values) ? $totals_values[$nt] : $totals_values);
			}
		}
		
		// Mark the column as up to date
		$this->get_column($column_name)->set_fresh(true);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::get_cell_value()
	 */
	public function get_cell_value($column_name, $row_number){
		$data_row_cnt = $this->count_rows_loaded();
		if ($row_number >= $data_row_cnt){
			return $this->totals_rows[$row_number - $data_row_cnt][$column_name];
		}
		return $this->rows[$row_number][$column_name];
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::set_cell_value()
	 */
	public function set_cell_value($column_name, $row_number, $value){
		// Create the column, if not already there
		if (!$this->get_column($column_name)){
			$this->get_columns()->add_from_expression($column_name);
		}
		
		// Detect, if the cell belongs to a total row
		$data_row_cnt = $this->count_rows_loaded();
		if ($row_number >= $data_row_cnt && $row_number < $this->count_rows_loaded(true)){
			$this->totals_rows[$row_number - $data_row_cnt][$column_name] = $value;
		}
		
		// Set the cell valu in the data matrix
		$this->rows[$row_number][$column_name] = $value;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::get_total_value()
	 */
	public function get_total_value($column_name, $row_number){
		return $this->totals_rows[$row_number][$column_name];
	}
	
	/**
	 * 
	 * @param DataColumnInterface $col
	 * @param \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder $query
	 */
	protected function get_data_for_column(DataColumnInterface $col, \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder $query){
		// add the required attributes
		foreach ($col->get_expression_obj()->get_required_attributes() as $attr){
			try {
				$attribute = $this->get_meta_object()->get_attribute($attr);
			} catch (MetaAttributeNotFoundError $e) {
				continue;
			}
			// if the attributes data source is the same, as the one of the main object, add the attribute to the query
			if ($attribute->get_object()->get_data_source_id() == $this->get_meta_object()->get_data_source_id()){
				// if a formula is applied to the attribute, get all attributes required for the formula
				// if it is just a plain attribute, add it and nothing else
				if ($expr = $attribute->get_formula()){
					if ($expr->is_formula()) {
						$expr = $attribute->get_data_expression();
						$expr->set_relation_path($attribute->get_relation_path()->to_string());
						$this->get_columns()->add_from_expression($expr, $attr);
						$this->get_data_for_column($this->get_column($attr), $query);
					} 
				} else {
					$query->add_attribute($attr);
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
				$rels = RelationPath::relation_path_parse($attribute->get_alias_with_relation_path());
				foreach ($rels as $depth => $rel){
					$rel_path = RelationPath::relation_path_add($last_rel_path, $rel);
					if ($this->get_meta_object()->get_related_object($rel_path)->get_data_source_id() == $this->get_meta_object()->get_data_source_id()){
						$rel_path_in_main_ds = $last_rel_path;
					} else {
						if (!$rel_path_to_subsheet){
							// Remember the path to the relation to the object with the other data source
							$rel_path_to_subsheet = $rel_path;
						} else {
							// All path parts following the one to the other data source, go into the subsheet
							$rel_path_in_subsheet = RelationPath::relation_path_add($rel_path_in_subsheet, $rel);
						}
					}
					$last_rel_path = $rel_path;
					if ($depth == (count($rels) - 2)) break; // stop one path step before the end because that would be the attribute of the related object
				}
				// Create a subsheet for the relation if not yet existent and add the required attribute
				if (!$subsheet = $this->get_subsheets()->get($rel_path_to_subsheet)){
					$subsheet_object = $this->get_meta_object()->get_related_object($rel_path_to_subsheet);
					$subsheet = DataSheetSubsheetFactory::create_for_object($subsheet_object, $this);
					$this->get_subsheets()->add($subsheet, $rel_path_to_subsheet);
					if (!$this->get_meta_object()->get_relation($rel_path_to_subsheet)->is_reverse_relation()){
						// add the foreign key to the main query and to this sheet
						$query->add_attribute($rel_path_to_subsheet);
						// IDEA do we need to add the column to the sheet? This is just useless data...
						// Additionally it would make trouble when the column has formatters...
					
						$this->get_columns()->add_from_expression($rel_path_to_subsheet, '', true);
					}
				}
				// Add the current attribute to the subsheet prefixing it with it's relation path relative to the subsheet's object
				$subsheet->get_columns()->add_from_expression(RelationPath::relation_path_add($rel_path_in_subsheet, $attribute->get_alias()));
				// Add the related object key alias of the relation to the subsheet to that subsheet. This will be the right key in the future JOIN.
				if ($rel_path_to_subsheet_right_key = $this->get_meta_object()->get_relation($rel_path_to_subsheet)->get_related_object_key_alias()){
					$subsheet->get_columns()->add_from_expression(RelationPath::relation_path_add($rel_path_in_main_ds, $rel_path_to_subsheet_right_key));
				} else {
					throw new DataSheetUidColumnNotFoundError($this, 'Cannot find UID (primary key) for subsheet: no key alias can be determined for the relation "' . $rel_path_to_subsheet . '" from "' . $this->get_meta_object()->get_alias_with_namespace() . '" to "' . $this->get_meta_object()->get_relation($rel_path_to_subsheet)->get_related_object()->get_alias_with_namespace() . '"!');
				}
			}
			
			if ($attribute->get_formatter()){
				$col->set_formatter($attribute->get_formatter());
				$col->get_formatter()->set_relation_path($attribute->get_relation_path()->to_string());
				if ($aggregator = DataAggregator::get_aggregate_function_from_alias($col->get_expression_obj()->to_string())){
					$col->get_formatter()->map_attribute(str_replace(':'.$aggregator, '', $col->get_expression_obj()->to_string()), $col->get_expression_obj()->to_string());
				}
				foreach ($col->get_formatter()->get_required_attributes() as $req){
					if (!$this->get_column($req)){
						$column = $this->get_columns()->add_from_expression($req, '', true);
						$this->get_data_for_column($column, $query);
					}
				}
			}
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_read()
	 */
	public function data_read($limit = null, $offset = null){
		// Empty the data before reading
		// IDEA Enable incremental reading by distinguishing between reading the same page an reading a new page
		$this->remove_rows();
		
		if (is_null($limit)) $limit = $this->get_rows_on_page();
		if (is_null($offset)) $offset = $this->get_row_offset();
		
		// create new query for the main object
		$query = QueryBuilderFactory::create_from_alias($this->exface, $this->get_meta_object()->get_query_builder());
		$query->set_main_object($this->get_meta_object());
	
		// Add columns to the query
		if (empty($this->cols)){
			// TODO get default attributes from meta object
		}
		
		foreach ($this->get_columns() as $col){
			$this->get_data_for_column($col, $query);
			foreach ($col->get_totals()->get_all() as $row => $total){
				$query->add_total($col->get_attribute_alias(), $total->get_function(), $row);
			}
		}
	
		// Ensure, the columns with system attributes are always in the select
		// FIXME With growing numbers of behaviors and system attributes, this becomes a pain, as more and more possibly
		// aggregated columns are added automatically - even if the sheet is only meant for reading. Maybe we should let
		// the code creating the sheet add the system columns. The behaviors will prduce errors if this does not happen anyway.
		foreach ($this->get_meta_object()->get_attributes()->get_system()->get_all() as $attr){
			if (!$this->get_columns()->get_by_attribute($attr)){
				// Check if the system attribute has a default aggregator if the data sheet is being aggregated
				if ($this->has_aggregators() && $attr->get_default_aggregate_function()){
					$col = $this->get_columns()->add_from_expression($attr->get_alias() . ':' . $attr->get_default_aggregate_function());
				} else {
					$col = $this->get_columns()->add_from_attribute($attr);
				}
				$this->get_data_for_column($col, $query);
			}
		}
		
		// Set explicitly defined filters
		$query->set_filters_condition_group($this->get_filters());
		// Add filters from the contexts
		foreach($this->exface->context()->get_scope_application()->get_filter_context()->get_conditions($this->get_meta_object()) as $cond){
			$query->add_filter_condition($cond);
		}
		
		// set aggregations
		foreach ($this->get_aggregators() as $aggr){
			$query->add_aggregation($aggr->get_attribute_alias());
		}
		
		// set sorting
		$sorters = $this->has_sorters() ? $this->get_sorters() : $this->get_meta_object()->get_default_sorters();
		foreach ($sorters as $sorter){
			$query->add_sorter($sorter->get_attribute_alias(), $sorter->get_direction());
		}
		
		if ($limit > 0){
			$query->set_limit($limit, $offset);
		}
		
		try {
			$result = $query->read($this->get_meta_object()->get_data_connection());
		} catch (\Throwable $e){
			throw new DataSheetReadError($this, $e->getMessage(), null, $e);
		}

		$this->add_rows($query->get_result_rows());
		$this->totals_rows = $query->get_result_totals();
		$this->total_row_count = $query->get_result_total_rows();
		
		// load data for subsheets if needed
		/* @var $subsheet DataSheet */
		foreach ($this->get_subsheets() as $rel_path => $subsheet){
			if (!$this->get_meta_object()->get_relation($rel_path)->is_reverse_relation()){
				$foreign_keys = $this->get_column_values($rel_path);
				$subsheet->add_filter_from_string($this->get_meta_object()->get_relation($rel_path)->get_related_object_key_alias(), implode(EXF_LIST_SEPARATOR, array_unique($foreign_keys)), EXF_COMPARATOR_IN);
				$left_key_column = $rel_path;
				$right_key_column = $this->get_meta_object()->get_relation($rel_path)->get_related_object_key_alias();
			} else {
				if ($this->get_meta_object()->get_relation($rel_path)->get_main_object_key_attribute()){
					throw new DataSheetJoinError($this, 'Joining subsheets via reverse relations with explicitly specified foreign keys, not implemented yet!', '6T5V36I');
				} else {
					$foreign_keys = $this->get_uid_column()->get_values();
					$subsheet->add_filter_from_string($this->get_meta_object()->get_relation($rel_path)->get_foreign_key_alias(), implode(EXF_LIST_SEPARATOR, array_unique($foreign_keys)), EXF_COMPARATOR_IN);
					// FIXME Fix Reverse relations key bug. Getting the left key column from the reversed relation here is a crude hack, but 
					// the get_main_object_key_alias() strangely does not work for reverse relations
					$left_key_column = $this->get_meta_object()->get_relation($rel_path)->get_reversed_relation()->get_related_object_key_alias();
					$right_key_column = $this->get_meta_object()->get_relation($rel_path)->get_foreign_key_alias();
				}				
			}
			$subsheet->data_read();
			// add the columns from the sub-sheets, but prefix their names with the relation alias, because they came from this relation!
			$this->join_left($subsheet, $left_key_column, $right_key_column, $rel_path);
		}
		
		foreach ($this->get_columns() as $name => $col){
			$vals = $col->get_expression_obj()->evaluate($this, $name);
			if (is_array($vals)){
				// See if the expression returned more results, than there were rows. If so, it was also performed on 
				// the total rows. In this case, we need to slice them off and pass to set_column_values() separately.
				// This only works, because evaluating an expression cannot change the number of data rows! This justifies
				// the assumption, that any values after count_rows() must be total values.
				if ($this->count_rows() < count($vals)) {
					$totals = array_slice($vals, $this->count_rows());
					$vals = array_slice($vals, 0, $this->count_rows());
				}
			}
			$this->set_column_values($name, $vals, $totals);
		}
		return $result;
	}
	
	public function count_rows(){
		return count($this->rows);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_save()
	 */
	public function data_save(DataTransactionInterface $transaction = null){		
		return $this->data_update(true, $transaction);
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_update()
	 */
	public function data_update($create_if_uid_not_found = false, DataTransactionInterface $transaction = null){
		$counter = 0;
		
		// Start a new transaction, if not given
		if (!$transaction){
			$transaction = $this->get_workbench()->data()->start_transaction();
			$commit = true;
		} else {
			$commit = false;
		}
	
		// Check if the data source already contains rows with matching UIDs
		// TODO do not update rows, that were created here. Currently they are created and immediately updated afterwards.
		if ($create_if_uid_not_found){
			if ($this->get_uid_column()){
				// Create another data sheet selecting those UIDs currently present in the data source
				$uid_check_ds = DataSheetFactory::create_from_object($this->get_meta_object());
				$uid_column = $this->get_uid_column()->copy();
				$uid_check_ds->get_columns()->add($uid_column);
				$uid_check_ds->add_filter_from_column_values($this->get_uid_column());
				$uid_check_ds->data_read();
				$missing_uids = $this->get_uid_column()->diff_values($uid_check_ds->get_uid_column());
				if (count($missing_uids) > 0){
					$create_ds = $this->copy()->remove_rows();
					foreach($missing_uids as $missing_uid){
						$create_ds->add_row($this->get_row_by_column_value($this->get_uid_column()->get_name(), $missing_uid));
					}
					$counter += $create_ds->data_create(false, $transaction);
				}
			} else {
				throw new DataSheetWriteError($this, 'Creating rows from an update statement without a UID-column not supported yet!', '6T5VBHF');
			}
		}
		
		// After all preparation is done, check to see if there are any rows to update left
		if ($this->is_empty()){
			return 0;
		}
		
		// Now the actual updating starts
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'UpdateData.Before'));
		
		// Add columns with fixed values to the data sheet
		$processed_relations = array();
		foreach ($this->get_columns() as $col){
			if (!$col->get_attribute()){
				//throw new MetaAttributeNotFoundError($this->get_meta_object(), 'Cannot find attribute for data sheet column "' . $col->get_name() . '"!');
				continue;
			}
			// Fetch all attributes with fixed values and add them to the sheet if not already there
			$rel_path = $col->get_attribute()->get_relation_path()->to_string();
			if ($processed_relations[$rel_path]) continue;
			/* @var $attr \exface\Core\CommonLogic\Model\attribute */
			foreach ($col->get_attribute()->get_object()->get_attributes() as $attr){
				if ($expr = $attr->get_fixed_value()){
					$alias_with_relation_path = RelationPath::relation_path_add($rel_path, $attr->get_alias());
					if (!$col = $this->get_column($alias_with_relation_path)){
						$col = $this->get_columns()->add_from_expression($alias_with_relation_path, NULL, true);
					} elseif ($col->get_ignore_fixed_values()){
						continue;
					}
					$col->set_values_by_expression($expr);
				}
			}
			$processed_relations[$rel_path] = true;
		}
		
		// Create a query
		$query = QueryBuilderFactory::create_from_alias($this->exface, $this->get_meta_object()->get_query_builder());
		$query->set_main_object($this->get_meta_object());
		// Add filters to the query
		$query->set_filters_condition_group($this->get_filters());
		
		// Add values
		// At this point, it is important to understand, that there are different types of update data sheets possible:
		// - A "regular" sheet with one row per object identified by the UID column. In this case, that object needs to be updated by values from
		//   the corresponding columns
		// - A data sheet with a single row and no UID column, where the values of that row should be saved to all object matching the filter
		// - A data sheet with a single row and a UID column, where the one row references multiple object explicitly selected by the user (the UID
		//   column will have one cell with a list of UIDs in this case.
		foreach ($this->get_columns() as $column){
			// Skip columns, that do not represent a meta attribute
			if (!$column->get_expression_obj()->is_meta_attribute()) {
				continue;
			} elseif (!$column->get_attribute()) {
				// Skip columns, that reference non existing attributes
				// TODO Is throwing an exception appropriate here?
				throw new MetaAttributeNotFoundError($this->get_meta_object(), 'Attribute "' . $column->get_expression_obj()->to_string() . '" of object "' . $this->get_meta_object()->get_alias_with_namespace() . '" not found!');
			} elseif (DataAggregator::get_aggregate_function_from_alias($column->get_expression_obj()->to_string())) {
				// Skip columns with aggregate functions
				continue;
			}
			
			// Use the UID column as a filter to make sure, only these rows are affected
			if ($column->get_attribute()->get_alias_with_relation_path() == $this->get_meta_object()->get_uid_alias()){
				$query->add_filter_from_string($this->get_meta_object()->get_uid_alias(), implode(EXF_LIST_SEPARATOR, array_unique($column->get_values(false))), EXF_COMPARATOR_IN);
			} else {
				// Add all other columns to values
				
				// First check, if the attribute belongs to a related object
				if ($rel_path = $column->get_attribute()->get_relation_path()->to_string()){
					if ($this->get_meta_object()->get_relation($rel_path)->get_type() == 'n1'){
						$uid_column_alias = $rel_path;
					} else {
						//$uid_column = $this->get_column($this->get_meta_object()->get_relation($rel_path)->get_main_object_key_attribute()->get_alias_with_relation_path());
						throw new DataSheetWriteError($this, 'Updating attributes from reverse relations ("' . $column->get_expression_obj()->to_string() . '") is not supported yet!', '6T5V4HW');	
					}
				} else {
					$uid_column_alias = $this->get_meta_object()->get_uid_alias();
				}
				
				// If it is a direct attribute, add it to the query
				if ($this->get_uid_column()){
					// If the data sheet has separate values per row (identified by the UID column), add all the values to the query.
					// In this case, each object will get its own value. However, we need to ensure, that there are UIDs for each value,
					// even if the value belongs to a related object. If there is no appropriate UID column for updated related object,
					// the UID values must be fetched from the data source using an identical data sheet, but having only the required uid column.
					// Since the new data sheet is cloned, it will have exactly the same filters, order, etc. so we can be sure to fetch only those
					// UIDs, that should have been in the original sheet. Additionally we need to add a filter over the values of the original UID
					// column, in case the user had explicitly selected some of the rows of the original data set.
					if (!$uid_column = $this->get_column($uid_column_alias)){
						$uid_data_sheet = $this->copy();
						$uid_data_sheet->get_columns()->remove_all();
						$uid_data_sheet->get_columns()->add_from_expression($this->get_meta_object()->get_uid_alias());
						$uid_data_sheet->get_columns()->add_from_expression($uid_column_alias);
						$uid_data_sheet->add_filter_from_string($this->get_meta_object()->get_uid_alias(), implode($this->get_uid_column()->get_values(), EXF_LIST_SEPARATOR), EXF_COMPARATOR_IN);
						$uid_data_sheet->data_read();
						$uid_column = $uid_data_sheet->get_column($uid_column_alias);	
					}
					$query->add_values($column->get_expression_obj()->to_string(), $column->get_values(false), $uid_column->get_values(false));
				} else {
					// If there is only one value for the entire data sheet (no UIDs gived), add it to the query as a single column value.
					// In this case all object matching the filter will get updated by this value
					$query->add_value($column->get_expression_obj()->to_string(), $column->get_values(false)[0]);
				}
			}
		}
		
		// Run the query
		$connection = $this->get_meta_object()->get_data_connection();
		$transaction->add_data_connection($connection);
		try {
			$counter += $query->update($connection);
		} catch (\Throwable $e){
			$transaction->rollback();
			$commit = false;
			throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), null, $e);
		}
		
		if ($commit  && !$transaction->is_rolled_back()){
			$transaction->commit();
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'UpdateData.After'));
		
		return $counter;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_replace_by_filters()
	 */
	public function data_replace_by_filters(DataTransactionInterface $transaction = null, $delete_redundant_rows = true, $update_by_uid_ignoring_filters = true){
		// Start a new transaction, if not given
		if (!$transaction){
			$transaction = $this->get_workbench()->data()->start_transaction();
			$commit = true;
		} else {
			$commit = false;
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'ReplaceData.Before'));
		
		$counter = 0;
		if ($delete_redundant_rows){
			if ($this->get_filters()->is_empty()){
				throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data if no filter are defined! This would delete ALL data for the object "' . $this->get_meta_object()->get_alias_with_namespace() . '"!', '6T5V4TS');
			}
			if ($this->get_uid_column()){
				$redundant_rows_ds = $this->copy();
				$redundant_rows_ds->get_columns()->remove_all();
				$uid_column = $this->get_uid_column()->copy();
				$redundant_rows_ds->get_columns()->add($uid_column);
				$redundant_rows_ds->data_read();
				$redundant_rows = $redundant_rows_ds->get_uid_column()->diff_values($this->get_uid_column());
				if (count($redundant_rows) > 0){
					$delete_ds = DataSheetFactory::create_from_object($this->get_meta_object());
					$delete_col = $uid_column->copy();
					$delete_ds->get_columns()->add($delete_col);
					$delete_ds->get_uid_column()->remove_rows()->set_values(array_values($redundant_rows));
					$counter += $delete_ds->data_delete($transaction);
				}
			} else {
				throw new DataSheetWriteError($this, 'Cannot delete redundant rows while replacing data for "' . $this->get_meta_object()->get_alias_with_namespace() . '" if no UID column is present in the data sheet', '6T5V5EB');
			}
		}
		
		// If we need to update records by UID and we have a non-empty UID column, we need to remove all filters to make sure the update
		// runs via UID only. Thus, the update is being performed on a copy of the sheet, which does not have any filters. In all other
		// cases, the update should be performed on the original data sheet itself.
		if ($update_by_uid_ignoring_filters && $this->get_uid_column() && !$this->get_uid_column()->is_empty()){
			$update_ds = $this->copy();
			$update_ds->get_filters()->remove_all();
		} else {
			$update_ds = $this;
		}
		
		$counter += $update_ds->data_update(true, $transaction);
		
		if ($commit  && !$transaction->is_rolled_back()){
			$transaction->commit();
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'ReplaceData.After'));
		
		return $counter;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_create()
	 */
	public function data_create($update_if_uid_found = true, DataTransactionInterface $transaction = null){
		// Start a new transaction, if not given
		if (!$transaction){
			$transaction = $this->get_workbench()->data()->start_transaction();
			$commit = true;
		} else {
			$commit = false;
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'CreateData.Before'));
		// Create a query
		$query = QueryBuilderFactory::create_from_alias($this->exface, $this->get_meta_object()->get_query_builder());
		$query->set_main_object($this->get_meta_object());
		
		// Add values for columns based on attributes with defaults or fixed values
		foreach ($this->get_meta_object()->get_attributes()->get_all() as $attr){
			if ($def = ($attr->get_default_value() ? $attr->get_default_value() : $attr->get_fixed_value())){
				if (!$col = $this->get_columns()->get_by_attribute($attr)){
					$col = $this->get_columns()->add_from_expression($attr->get_alias());
				}
				$col->set_values_from_defaults();
			}
		}
		
		// Check, if all required attributes are present
		foreach($this->get_meta_object()->get_attributes()->get_required() as $req){
			if (!$req_col = $this->get_columns()->get_by_attribute($req)){
				// If there is no column for the required attribute, add one
				$col = $this->get_columns()->add_from_expression($req->get_alias());
				// First see if there are default values for this column
				if ($def = ($req->get_default_value() ? $req->get_default_value() : $req->get_fixed_value() )){
					$col->set_values_by_expression($def);
				} else {
					// Try to get the value from the current filter contexts: if the missing attribute was used as a direct filter, we assume, that the data is saved
					// in the same context, so we can set the attribute value to the filter value
					// TODO Look in other context scopes, not only in the application scope. Still looking for an elegant solution here.
					foreach($this->exface->context()->get_scope_application()->get_filter_context()->get_conditions($this->get_meta_object()) as $cond){
						if ($req->get_alias() == $cond->get_expression()->to_string()
						&& ($cond->get_comparator() == EXF_COMPARATOR_EQUALS || $cond->get_comparator() == EXF_COMPARATOR_IS)){
							$this->set_column_values($req->get_alias(), $cond->get_value());
						}
					}
				}
			} else {
				$req_col->set_values_from_defaults();
			}
		}
		
		// Add values
		foreach ($this->get_columns() as $column){
			// Skip columns, that do not represent a meta attribute
			if (!$column->get_expression_obj()->is_meta_attribute()) continue;
			// Check if the meta attribute really exists
			if (!$column->get_attribute()){
				throw new MetaAttributeNotFoundError($this->get_meta_object(), 'Cannot find attribute for data sheet column "' . $column->get_name() . '"!');
				continue;
			}
				
			// Check the uid column for values. If there, it's an update!
			if ($column->get_attribute()->get_alias() == $this->get_meta_object()->get_uid_alias() && $update_if_uid_found){
				// TODO
			} else {
				// Add all other columns to values
				$query->add_values($column->get_expression_obj()->to_string(), $column->get_values(false));
			}
		}
		
		// Run the query
		$connection = $this->get_meta_object()->get_data_connection();
		$transaction->add_data_connection($connection);
		try {
			$new_uids = $query->create($connection);
		} catch (\Throwable $e) {
			$transaction->rollback();
			$commit = false;
			throw new DataSheetWriteError($this, $e->getMessage(), null, $e);
		}
		
		if ($commit  && !$transaction->is_rolled_back()){
			$transaction->commit();
		}
		
		// Save the new UIDs in the data sheet
		$this->set_column_values($this->get_meta_object()->get_uid_alias(), $new_uids);
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'CreateData.After'));
		
		return count($new_uids);
	}
	
	/**
	 * TODO Ask the user before a cascading delete!
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_delete()
	 */
	public function data_delete(DataTransactionInterface $transaction = null){
		// Start a new transaction, if not given
		if (!$transaction){
			$transaction = $this->get_workbench()->data()->start_transaction();
			$commit = true;
		} else {
			$commit = false;
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'DeleteData.Before'));
		
		$affected_rows = 0;
		// create new query for the main object
		$query = QueryBuilderFactory::create_from_alias($this->exface, $this->get_meta_object()->get_query_builder());
		$query->set_main_object($this->get_meta_object());
		
		if ($this->is_unfiltered()) {
			throw new DataSheetWriteError($this, 'Cannot delete all instances of "' . $this->get_meta_object()->get_alias_with_namespace() . '": forbidden operation!', '6T5VCA6');
		}
		// set filters
		$query->set_filters_condition_group($this->get_filters());
		if ($this->get_uid_column()){
			if ($uids = $this->get_uid_column()->get_values(false)){
				$query->add_filter_condition(ConditionFactory::create_from_expression($this->exface, $this->get_uid_column()->get_expression_obj(), implode(EXF_LIST_SEPARATOR, $uids), EXF_COMPARATOR_IN));
			}
		} 
		
		// Check if there are dependent object, that require cascading deletes
		foreach ($this->get_subsheets_for_cascading_delete() as $ds){
			// Just perform the delete if there really is some data to delete. This sure means an extra data source connection, but
			// preventing delete operations on empty data sheets also prevents calculating their cascading deletes, etc. This saves
			// a lot of iterations and reduces the risc of unwanted deletes due to some unforseeable filter constellations.
			
			// First check if the sheet theoretically can have data - that is, if it has UIDs in it's rows or at least some filters
			// This makes sure, reading data in the next step will not return the entire table, which would then get deleted of course!
			if ((!$ds->get_uid_column() || $ds->get_uid_column()->is_empty()) && $ds->get_filters()->is_empty()) continue;
			// If the there can be data, but there are no rows, read the data
			if ($ds->data_read()){
				$ds->data_delete($transaction);
			}
		}
		
		// run the query
		$connection = $this->get_meta_object()->get_data_connection();
		$transaction->add_data_connection($connection);
		try {
			$affected_rows += $query->delete($connection);
		} catch (\Throwable $e){
			$transaction->rollback();
			throw new DataSheetWriteError($this, 'Data source error. ' . $e->getMessage(), null, $e);
		}
		
		if ($commit && !$transaction->is_rolled_back()){
			$transaction->commit();
		}
		
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'DeleteData.After'));
		
		return $affected_rows;
	}
	
	/**
	 * Returns an array with data sheets containig all instances, that would need to be deleted cascadingly if the
	 * data of this sheet would be deleted.
	 * @return DataSheetInterface[]
	 */
	public function get_subsheets_for_cascading_delete(){
		$subsheets = array();
		// Check if there are dependent objects, that require cascading deletes
		// This is the case, if the deleted object has reverse relations (1-to-many), where the relation is a mandatory
		// attribute of the related object (that is, if the related object cannot exist without the one we are deleting)
		/* @var $rel \exface\Core\CommonLogic\Model\Relation */
		foreach ($this->get_meta_object()->get_relations('1n') as $rel){
			// FIXME use $rel->get_related_object_key_attribute() here instead. This must be fixed first though, as it returns false now
			if (!$rel->get_related_object()->get_attribute($rel->get_foreign_key_alias())->is_required()){
				// FIXME Throw a warning here! Need to be able to show warning along with success messages!
				//throw new DataSheetWriteError($this, 'Cascading deletion via optional relations not yet implemented: no instances were deleted for relation "' . $rel->get_alias() . '" to object "' . $rel->get_related_object()->get_alias_with_namespace() . '"!');
			} else {
				$ds = DataSheetFactory::create_from_object($rel->get_related_object());
				// Use all filters of the original query in the cascading queries
				$ds->set_filters($this->get_filters()->rebase($rel->get_alias()));
				// Additionally add a filter over UIDs in the original query, if it has data with UIDs. This makes sure, the cascading deletes
				// only affect the loaded rows and nothing "invisible" to the user!
				if ($this->get_uid_column()){
					$uids = $this->get_uid_column()->get_values(false);
					if (count($uids) > 0){
						$ds->add_filter_in_from_string($this->get_uid_column()->get_expression_obj()->rebase($rel->get_alias())->to_string(), $uids);
					}
				}
				$subsheets[] = $ds;
			}
		}
		return $subsheets;
	}
	
	/**
	 * @return DataSheetList
	 */
	public function get_subsheets(){
		return $this->subsheets;
	}
	
	/**
	 * Creates a new condition and adds it to the filters of this data sheet to the root condition group.
	 * FIXME Make ConditionGroup::add_conditions_from_string() better usable by introducing the base object there. Then
	 * remove this method here.
	 * @param string $column_name
	 * @param ambiguos $value
	 * @param string $comparator
	 */
	function add_filter_from_string($expression_string, $value, $comparator = null){
		$base_object = $this->get_meta_object();
		$this->get_filters()->add_conditions_from_string($base_object, $expression_string, $value, $comparator);		
		return $this;
	}
	
	/**
	 * Adds an filter based on a list of values: the column value must equal one of the values in the list.
	 * The list may be an array or a comma separated string
	 * FIXME move to ConditionGroup, so it can be used for nested groups too!
	 * @param string $column
	 * @param string|array $values
	 */
	function add_filter_in_from_string($column, $value_list){
		if (is_array($value_list)){
			$value = implode(EXF_LIST_SEPARATOR, $value_list);
		} else {
			$value = $value_list;
		}
		$this->add_filter_from_string($column, $value, EXF_COMPARATOR_IN);
	}
	
	/**
	 * Adds an filter based on a list of values: the column value must equal one of the values in the list.
	 * The list may be an array or a comma separated string
	 * FIXME move to ConditionGroup, so it can be used for nested groups too!
	 * @param string $column
	 * @param string|array $values
	 */
	function add_filter_is_from_string($column, $value_list){
		if (is_array($value_list)){
			$value = implode(EXF_LIST_SEPARATOR, $value_list);
		} else {
			$value = $value_list;
		}
		$this->add_filter_from_string($column, $value, EXF_COMPARATOR_IN);
	}
	
	/**
	 * Returns an array of data sorters
	 * @return DataSorterListInterface
	 */
	function get_sorters(){
		return $this->sorters;
	}
	
	/**
	 * Returns TRUE if the data sheet has at least one sorter and FALSE otherwise
	 * @return boolean
	 */
	public function has_sorters(){
		if ($this->get_sorters()->count() > 0){
			return true;
		} else {
			return false;
		}
	}
	
	function set_counter_rows_all($count){
		$this->total_row_count = intval($count);
	}
	
	/**
	 * Returns multiple rows of the data sheet as an array of associative array (e.g. [rownum => [col1 => val1, col2 => val2, ...] ])
	 * By default returns all rows. Use the arguments to select only a range of rows.
	 * @param number $how_many
	 * @param number $offset
	 * @return array
	 */
	function get_rows($how_many = 0, $offset = 0){
		$return = array();
		if ($how_many || $offset){
			foreach ($this->rows as $nr => $row){
				if ($nr >= $offset && $how_many < count($return)){
					$return[$nr] = $row;
				}
			}
		} else {
			$return = $this->rows;
		}
		return $return;
	}
	
	/**
	 * Returns the specified row as an associative array (e.g. [col1 => val1, col2 => val2, ...])
	 * @param number $row_number
	 * @return multitype:
	 */
	function get_row($row_number = 0){
		return $this->rows[$row_number];
	}
	
	/**
	 * Returns the first row, that contains a given value in the specified column. Returns NULL if no row matches.
	 * @param string $column_name
	 * @param mixed $value
	 * @throws DataSheetColumnNotFoundError
	 * @return array
	 */
	public function get_row_by_column_value($column_name, $value){
		$column = $this->get_column($column_name);
		if (!$column){
			throw new DataSheetColumnNotFoundError($this, 'Cannot find row by column value: invalid column name "' . $column_name . '"!');
		}
		return $this->get_row($column->find_row_by_value($value));
	}
	
	/**
	 * Returns the total rows as assotiative arrays. Multiple total rows can be used to display multiple totals per column.
	 * @return array [ column_id => total value ]
	 */
	function get_totals_rows(){
		return $this->totals_rows;
	}
	
	function count_rows_all(){
		return $this->total_row_count;
	}
	
	function count_rows_loaded($include_totals=false){
		$cnt = count($this->rows) + ($include_totals ? count($this->totals_rows) : 0);
		return $cnt;
	}
	
	/**
	 * Returns an array of DataColumns
	 * @return DataColumnList
	 */
	public function get_columns() {
		return $this->cols;
	}
	
	/**
	 * Replaces the columns of this data sheet with the given column list not changing anything in the rows!
	 * Use with care! This may cause inconsistensies or unwanted data reads!
	 * TODO This method seams to dangerous. Need to find out, when columns are actually marked out of date!
	 * @param DataColumnList $columns
	 */
	public function set_columns(DataColumnList $columns){
		$columns->set_parent($this);
		$this->cols = $columns;
		return $this;
	}
	
	public function remove_rows_for_column($column_name){
		foreach ($this->get_rows() as $id => $row){
			unset($this->rows[$id][$column_name]);
			if (count($this->rows[$id]) == 0){
				$this->remove_row($id);
			}
		}
		return $this;
	}
	
	/**
	 * Returns a data column object by column name. This is an alias for get_columns()->get($name)!
	 * FIXME Remove in favor of get_columns()->get($name). This method is just temporarily here as long as the
	 * strange bug with the wrong parent sheet is not fixed.
	 * @param string column name
	 * @return DataColumn
	 */
	public function get_column($name){
		if ($result = $this->get_columns()->get($name)){
			if ($result->get_data_sheet() !== $this){
				// TODO The next line is a workaround for a strange bug: calling $this->get_column('X')->set_values() would not update the data sheet and thus the result
				// of calling $this->get_column_values('X') was different from this->get_column('X')->get_values(). I have no idea why... This line sure fixes the problem
				// but it needs to be investigated at some point as it might also hit other parent-child-combinations!
				$result->set_data_sheet($this);
				throw new DataSheetRuntimeError($this, 'Column "' . $result->get_name() . '" belongs to the wrong data sheet!');
			}
			return $result;
		}
		return false;
	}
	
	/**
	 * Returns the data sheet column containing the UID values of the main object or false if the data sheet does not contain that column
	 * @return \exface\Core\Interfaces\DataSheets\DataColumnInterface
	 */
	public function get_uid_column(){
		return $this->get_columns()->get($this->get_uid_column_name());
	}
	
	public function get_meta_object(){
		return $this->meta_object;
	}
	
	/**
	 * @return DataAggregatorListInterface
	 */
	public function get_aggregators() {
		return $this->aggregators;
	}
	
	/**
	 * Returns TRUE if the data sheet has at least one aggregator and FALSE otherwise
	 * @return boolean
	 */
	public function has_aggregators(){
		if ($this->get_aggregators()->count() > 0){
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the root condition group with all filters of the data sheet
	 * @return ConditionGroup
	 */
	public function get_filters() {
		return $this->filters;
	}
	
	public function set_filters(ConditionGroup $condition_group) {
		$this->filters = $condition_group;
	} 
	
	/**
	 * Returns a JSON representation of the data sheet with all it's data. This JSON can be used to recreate the data
	 * sheet later or just to make the data well readable. 
	 * @return string JSON
	 */
	public function to_uxon(){
		return $this->export_uxon_object()->to_json(true);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::export_uxon_object()
	 */
	public function export_uxon_object(){
		$output = new UxonObject();
		$output->object_alias = $this->get_meta_object()->get_alias_with_namespace();
		
		foreach ($this->get_columns() as $col){
			$output->columns[] = $col->export_uxon_object();
		}
		
		if (!$this->is_empty()){
			$output->rows = $this->get_rows();
		}
		
		$output->totals_rows = $this->get_totals_rows();
		$output->filters = $this->get_filters()->export_uxon_object();
		$output->rows_on_page = $this->get_rows_on_page();
		$output->row_offset = $this->get_row_offset();
		if ($this->has_sorters()){
			foreach ($this->get_sorters() as $sorter){
				$output->sorters[] = $sorter->export_uxon_object();
			}
		}
		if ($this->has_aggregators()){
			foreach ($this->get_aggregators() as $aggr){
				$output->aggregators[] = $aggr->export_uxon_object();
			}
		}
		return $output;
	}
	
	public function import_uxon_object(UxonObject $uxon){
		
		// Columns
		if (is_array($uxon->columns)){
			foreach ($uxon->columns as $col){
				if ($col instanceof UxonObject){
					$column = DataColumnFactory::create_from_uxon($this, $col);
					$this->get_columns()->add($column);
				} else {
					$this->get_columns()->add_from_expression($col);
				}
			}
		}
		
		// Rows
		if (is_array($uxon->rows)){
			$this->add_rows($uxon->rows);
		}
		
		// Totals - ony for backwards compatibilty for times, where the totals functions were 
		// defined outside the column definition.
		// IMPORTANT: This must happen AFTER columns and row were created, since totals are added to existing columns!
		if (is_array($uxon->totals_functions) || $uxon->totals_functions instanceof \stdClass){
			foreach ((array) $uxon->totals_functions as $column_name => $functions){
				if (!$column = $this->get_columns()->get($column_name)){
					$column = $this->get_columns()->add_from_expression($column_name);
				}
				if (is_array($functions)){
					foreach ($functions as $func){
						$total = DataColumnTotalsFactory::create_from_string($column, $func->function);
						$column->get_totals()->add($total);
					}
				} else {
					$total = DataColumnTotalsFactory::create_from_string($column, $func->function);
					$column->get_totals()->add($total);
				}
			}
		}
	
		if ($uxon->filters){
			$this->set_filters(ConditionGroupFactory::create_from_object_or_array($this->exface, $uxon->filters));
		}
		
		if (isset($uxon->rows_on_page)){
			$this->set_rows_on_page($uxon->rows_on_page);
		}
		
		if (isset($uxon->row_offset)){
			$this->set_row_offset($uxon->row_offset);
		}
		
		if (is_array($uxon->sorters)){
			$this->get_sorters()->import_uxon_array($uxon->sorters);
		}
		if (is_array($uxon->aggregators)){
			$this->get_aggregators()->import_uxon_array($uxon->aggregators);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::remove_rows()
	 */
	public function remove_rows(){
		$this->rows = array();
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::remove_row()
	 */
	public function remove_row($row_number){
		unset($this->rows[$row_number]);
		return $this;
	}
	
	public function add_filter_from_column_values(DataColumnInterface $column){
		$this->add_filter_from_string($column->get_expression_obj()->to_string(), implode(EXF_LIST_SEPARATOR, array_unique($column->get_values(false))), EXF_COMPARATOR_IN);
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::is_empty()
	 */
	public function is_empty(){
		if (count($this->get_rows()) < 1){
			return true;
		} else {
			return false;
		}
	}
	
	protected function set_fresh($value){
		foreach ($this->get_columns() as $col){
			$col->set_fresh($value);
		}
		return $this;
	}
	
	public function is_fresh(){
		foreach ($this->get_columns() as $col){
			if ($col->is_fresh() === false){
				return false;
			}
		}
		return true;
	}
	
	public function get_rows_on_page() {
		return $this->rows_on_page;
	}
	
	public function set_rows_on_page($value) {
		$this->rows_on_page = $value;
		return $this;
	} 

	public function get_row_offset() {
		return $this->row_offset;
	}
	
	public function set_row_offset($value) {
		$this->row_offset = $value;
		return $this;
	}
	
	/**
	 * Merges the current data sheet with another one. Values of the other sheet will overwrite values of identical columns of the current one!
	 * @param DataSheet $other_sheet
	 */
	public function merge(DataSheetInterface $other_sheet){
		// Ignore empty other sheets
		if ($other_sheet->is_empty() && $other_sheet->get_filters()->is_empty()){
			return $this;
		}
		// Chek if both sheets are identical
		if ($this === $other_sheet){
			return $this;
		}		
		// Check if the sheets are based on the same object
		if ($this->get_meta_object()->get_id() !== $other_sheet->get_meta_object()->get_id()){
			throw new DataSheetMergeError($this, 'Cannot merge non-empty data sheets for different objects ("' . $this->get_meta_object()->get_alias_with_namespace() . '" and "' . $other_sheet->get_meta_object()->get_alias_with_namespace() . '"): not implemented!', '6T5E8GM');
		}
		// Check if both sheets have UID columns if they are not empty
		if ((!$this->is_empty() && !$this->get_uid_column()) || (!$other_sheet->is_empty() && !$other_sheet->get_uid_column())){
			if ($this->count_rows() == $other_sheet->count_rows()){
				$this->join_left($other_sheet);	
			} else {
				throw new DataSheetMergeError($this, 'Cannot merge data sheets without UID columns!', '6T5E8Q6');
			}
		}
		
		// TODO Merge filters too! Pay attention to the fact, that filters will be stored in the filter context,
		// so if this action is called again right away, they will come from different sources. It is important no
		// to dublicate them!
		
		// Merge columns
		$this->join_left($other_sheet, $this->get_meta_object()->get_uid_alias(), $this->get_meta_object()->get_uid_alias());
		
		return $this;
	}
	
	public function get_meta_object_relation_path(Object $related_object){
		// TODO First try to determine the path by searching for the related object among columns, filters, sorters, etc.
		// It is verly likely, that the user is interested in exactly the one relation already used! This is expecially important for
		// reverse relations, which can be ambiguous.
		return $this->get_meta_object()->find_relation_path($related_object);
	}
	
	/**
	 * Clones the data sheet and returns the new copy. The copy will point to the same meta object, but will
	 * have it's own columns, filters, aggregations, etc.
	 * @return DataSheetInterface
	 */
	public function copy(){
		$exface = $this->get_workbench();
		return DataSheetFactory::create_from_uxon($exface, $this->export_uxon_object());
	}

	/**
	 * @return exface
	 */
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::get_uid_column_name()
	 */
	public function get_uid_column_name() {
		if (!$this->uid_column_name){
			$this->uid_column_name = $this->get_meta_object()->get_uid_alias();
		}
		return $this->uid_column_name;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::set_uid_column_name()
	 */
	public function set_uid_column_name($value) {
		$this->uid_column_name = $value;
		return $this;
	} 
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::set_uid_column()
	 */
	public function set_uid_column(DataColumnInterface $column){
		$this->uid_column_name = $column->get_name();
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_validate()
	 */
	public function data_validate(){
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'ValidateData.Before'));
		if ($this->invalid_data_flag !== true){
			// TODO Add data type validation here
			$this->invalid_data_flag = false;
		}
		$this->get_workbench()->event_manager()->dispatch(EventFactory::create_data_sheet_event($this, 'ValidateData.After'));
		return $this->invalid_data_flag;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::data_mark_invalid()
	 */
	public function data_mark_invalid(){
		$this->invalid_data_flag = true;
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSheetInterface::is_unfiltered()
	 */
	public function is_unfiltered(){
		if ((!$this->get_uid_column() || $this->get_uid_column()->is_empty()) && $this->get_filters()->is_empty()) {
			return true;
		} else {
			return false;
		}
	}
}

?>