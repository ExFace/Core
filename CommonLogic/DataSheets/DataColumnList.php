<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Factories\DataColumnFactory;
use exface\Core\Interfaces\DataSheets\DataColumnListInterface;
use exface\Core\Interfaces\DataSheets\DataColumnInterface;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Exceptions\InvalidArgumentException;

class DataColumnList extends EntityList implements DataColumnListInterface {
	
	/**
	 * Adds a data sheet
	 * @param DataColumn $column
	 * @param mixed $key
	 * @return DataColumnListInterface
	 */
	public function add(&$column, $key = null, $overwrite_values = true){
		if (!($column instanceof DataColumn)){
			throw new InvalidArgumentException('Cannot add column to data sheet: only DataColumns can be added to the column list of a datasheet, "' . get_class($column) . '" given instead!');
		}
		$data_sheet = $this->get_data_sheet();
		if (!$this->get($column->get_name())){
			if ($column->get_data_sheet() !== $data_sheet){
				$col = $column->copy();
				$col->set_data_sheet($data_sheet);
			} else {
				$col = $column;
			}
			// Mark the data as outdated if new columns are added because the values for these columns should be fetched now
			$col->set_fresh(false);
			$result = parent::add($col, (is_null($key) && $col->get_name() ? $col->get_name() : $key));
		}
		
		if ($overwrite_values && $column->is_fresh()){
			$data_sheet->set_column_values($column->get_name(), $column->get_values());
		}
		
		return $result;
	}
	
	/**
	 * Add an array of columns. The array can contain DataColumns, expressions or a mixture of those
	 * @param array $columns
	 * @param string $relation_path
	 * @return DataColumnListInterface
	 */
	public function add_multiple(array $columns, $relation_path = '') {
		foreach ($columns as $col){
			if ($col instanceof DataColumn){
				$col_name = $relation_path ? RelationPath::relation_path_add($relation_path, $col->get_name()) : $col->get_name();
				if (!$this->get($col_name)){
					// Change the column name so it does not overwrite any existing columns
					$col->set_name($col_name);
					// Add the column (this will change the column's data sheet
					$this->add($col);
					// Modify the column's expression and overwrite the old one. Overwriting explicitly is important because
					// it will also update the attribute alias, etc.
					// FIXME perhaps it would be nicer to use the expression::rebase() here, but the relation path seems to 
					// be in the wrong direction here
					$col->set_expression($col->get_expression_obj()->set_relation_path($relation_path));
					// Update the formatter
					if ($col->get_formatter()){
						$col->get_formatter()->set_relation_path($relation_path);
					}
				}
			} else {
				$col_name = $relation_path ? RelationPath::relation_path_add($relation_path, $col) : $col;
				if (!$this->get($col_name)){
					try {
						$this->add_from_expression($col_name);
					} catch (\Exception $e){
						// TODO How to distinguish between unwanted garbage and bad column names?
					}
				}
			}
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::add_from_expression()
	 */
	public function add_from_expression($expression_or_string, $name='', $hidden=false){
		$data_sheet = $this->get_data_sheet();
		$col = DataColumnFactory::create_from_string($data_sheet, $expression_or_string, $name);
		$col->set_hidden($hidden);
		$this->add($col);
		return $col;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::add_from_attribute()
	 */
	public function add_from_attribute(Attribute $attribute){
		return $this->add_from_expression($attribute->get_alias_with_relation_path());
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataColumnListInterface::get_by_expression()
	 */
	public function get_by_expression($expression_or_string){
		if ($expression_or_string instanceof expression){
			$expression_or_string = $expression_or_string->to_string();
		}
		foreach ($this->get_all() as $col){
			if ($col->get_expression_obj()->to_string() == $expression_or_string){
				return $col;
			}
		}
		return false;
	}
	
	/**
	 * Returns the first column, that shows the specified attribute explicitly (not within a formula).
	 * Returns FALSE if no column is found.
	 * @param Attribute $attribute
	 * @return DataColumnInterface|boolean
	 */
	public function get_by_attribute(Attribute $attribute){
		foreach ($this->get_all() as $col){
			if ($col->get_attribute()
			&& $col->get_attribute()->get_alias_with_relation_path() == $attribute->get_alias_with_relation_path()){
				return $col;
			}
		}
		return false;
	}
	
	public function get_system(){
		$exface = $this->get_workbench();
		$parent = $this->get_parent();
		$result = new self($exface, $parent);
		foreach ($this->get_all() as $col){
			if ($col->get_attribute() && $col->get_attribute()->is_system()){
				$result->add($col);
			}
		}
		return $result;
	}
	
	/**
	 * Removes a column from the list completetly including it's values
	 * @param string $column_name
	 * @return DataColumnListInterface
	 */
	public function remove_by_key($column_name){
		parent::remove_by_key($column_name);
		$this->get_data_sheet()->remove_rows_for_column($column_name);
		
		// Make sure, the rows are reset if the last column is removed
		if ($this->is_empty()){
			$this->get_data_sheet()->remove_rows();
		}
	
		return $this;
	}
	
	/**
	 * Returns the parent data sheet (this method is a better understandable alias for get_parent())
	 * @return DataSheetInterface
	 */
	public function get_data_sheet(){
		return $this->get_parent();
	}
	
	/**
	 * Set the given data sheet as parent object for this column list and all it's columns
	 * @see \exface\Core\CommonLogic\EntityList::set_parent()
	 * @param DataSheetInterface $data_sheet
	 */
	public function set_parent(&$data_sheet){
		$result = parent::set_parent($data_sheet);
		foreach ($this->get_all() as $column){
			$column->set_data_sheet($data_sheet);
		}
		return $result;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::get_all()
	 * @return DataColumnInterface[]
	 */
	public function get_all(){
		return parent::get_all();
	}
}
?>