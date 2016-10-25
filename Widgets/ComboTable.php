<?php namespace exface\Core\Widgets;

use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;

/**
 * A ComboTable is an InputCombo, which uses a DataTable to show the autosuggest values. Thus, the user can see more
 * information about every suggested object. The ComboTable is very often used with relations, where the related object may
 * have many more data, then merely it's id (which is the value of the relation attribute).
 * 
 * The DataTable for autosuggests can either be genreated automatically based on the meta object, or specified by the user via
 * UXON or even extended from any other ready-made DataTable!
 * 
 * While not every UI-framework supports such a kind of widget, there are many ways to implement the main idea of the ComboTable:
 * showing more data about a selectable object in the autosuggest. Mobile templates might use cards like in Googles material design,
 * for example.
 * 
 * @author PATRIOT
 */
class ComboTable extends InputCombo implements iHaveChildren { 	
	/**
	 * @uxon text_column_id Id of the data column to be used as the displayed value (text) of the combo. 
	 * 
	 * If not set, text_attribute_alias will be used.
	 *  
	 * @var string
	 */
	private $text_column_id = null;
	/**
	 * @uxon value_column_id Id of the data column to be used as the internal value of the combo. If not set, value_attribute_alias will be used.
	 * @var string
	 */
	private $value_column_id = null;
	/**
	 * @uxon table 
	 * @var DataTable
	 */
	private $data_table = null;
	private $paginate = true;
	/**
	 * @uxon table_object_alias Alias of the meta object to be displayed in the table of the combo. 
	 * 
	 * By default it is the object of the ComboTable itself, but another object can be specified as well. This way combo tables
	 * can be easily used to select values from attributes of unrelated objects.
	 * 
	 * @var string
	 */
	private $table_object_alias = null;
	private $table_object = null;
	
	/**
	 * Returns the relation, this widget represents or FALSE if the widget stands for a direct attribute. 
	 * This shortcut function is very handy because a ComboTable often stands for a relation.
	 * @return \exface\Core\CommonLogic\Model\relation
	 */
	public function get_relation(){
		if ($this->get_attribute()->is_relation()){
			return $this->get_meta_object()->get_relation($this->get_attribute_alias());
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the DataTable, that is used for autosuggesting in a ComboTable or false if a DataTable cannot be created
	 * @return \exface\Core\Widgets\DataTable|boolean
	 */
	public function get_table(){
		// If the data table was not specified explicitly, attempt to create one from the attirbute_alias
		if (!$this->data_table){
			$this->init_table();
		}
		return $this->data_table;
	}
	
	protected function init_table(){
		// This will only work if there is an attribute_alias specified
		if (!$this->get_attribute_alias()){
			throw new UxonParserError('Cannot create a DataTable for a ComboTable before an attribute_alias for the Comobo is specified!');
			return false;
		}
			
		// Now, that we know, the attribute of this widget is a relation, we can create a default DataTable for the related object
		/* @var $table \exface\Core\Widgets\DataTable */
		$table = $this->get_page()->create_widget('DataTable', $this);
		$table->set_meta_object_id($this->get_table_object()->get_id());
		$table->add_columns_for_default_display_attributes();
		// Set some DataTable options needed to use it in a ComboTable
		$table->set_uid_column_id($this->get_value_column_id());
		$table->set_header_sort_multiple(false);
		$table->set_multi_select($this->get_multi_select());
		$table->set_lazy_loading($this->get_lazy_loading());
		$table->set_lazy_loading_action($this->get_lazy_loading_action());
		$table->set_paginate($this->get_paginate());
		$this->data_table = $table;
		// Ensure, that special columns needed for the ComboTable are present. This must be done after $this->data_table is
		// set, because the method may use autogeneration of the text column, which needs to know about the DataTable
		$this->add_combo_columns();
		return $table;
	}
	
	public function set_table($widget_or_uxon_object){
		// TODO let the user specify a custom table
	}
	
	/**
	 * Creates table columns for the value and text attributes of the combo and adds them to the table.
	 * NOTE: the columns are only added if they are not there already (= if they are not part of the default columns)
	 * and they will be automatically hidden, if the corresponding attribute is hidden!
	 * @return ComboTable
	 */
	protected function add_combo_columns(){
		$table = $this->get_table();
		$table_meta_object = $this->get_table()->get_meta_object();
		if (!$this->get_text_column_id()){
			if ($text_column = $this->get_table()->get_column_by_attribute_alias($this->get_text_attribute_alias())){
				$this->set_text_column_id($text_column->get_id());
			} else {
				$text_column = $table->create_column_from_attribute($table_meta_object->get_attribute($this->get_text_attribute_alias()));
				$this->set_text_column_id($text_column->get_id());
				$table->add_column($text_column);
			}
		}
		
		if (!$this->get_value_column_id()){
			if ($value_column = $this->get_table()->get_column_by_attribute_alias($this->get_value_attribute_alias())){
				$this->set_value_column_id($value_column->get_id());
			} else {
				$value_column = $table->create_column_from_attribute($table_meta_object->get_attribute($this->get_value_attribute_alias()), null, true);
				$this->set_value_column_id($value_column->get_id());
				$table->add_column($value_column);
			}
		}
		return $this;
	}
	 
	public function get_text_column_id() { 	
		return $this->text_column_id;
	}
	 
	public function set_text_column_id($value) {
		$this->text_column_id = $value;
	}
	
	/**
	 * Returns the column of the DataTable, where the text displayed in the combo will come from
	 * @return exface\Core\Widgets\DataColumn
	 */
	public function get_text_column(){
		return $this->get_table()->get_column($this->get_text_column_id());
	}
	
	public function get_value_column_id() {
		return $this->value_column_id;
	}
	
	public function set_value_column_id($value) {
		$this->value_column_id = $value;
		return $this;
	}
	
	/**
	 * Returns the column of the DataTable, where the value of the combo will come from
	 * @return exface\Core\Widgets\DataColumn
	 */
	public function get_value_column(){
		return $this->get_table()->get_column($this->get_value_column_id());
	}
	
	/**
	 * Prefills a ComboTable with the value it represents and the corresponding text. 
	 * @see \exface\Core\Widgets\Text::prefill()
	 */
	public function prefill(\exface\Core\Interfaces\DataSheets\DataSheetInterface $data_sheet, $relation_path_to_prefill_object=''){
		// Do not do anything, if the value is already set explicitly (e.g. a fixed value)
		if ($this->get_value()){
			return;
		}
		
		if (!$data_sheet->is_empty()){
			$this->set_prefill_data($data_sheet);
			if ($data_sheet->get_meta_object()->get_id() == $this->get_meta_object_id()){
				// If the prefill data is based on the same object, as the widget, simply look for the required attributes
				// in the prefill data.
				$this->set_value($data_sheet->get_cell_value($this->get_attribute_alias(), 0));
				
				// Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
				// but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
				// set it here. In most templates, setting merely the value of the combo well make the template load the
				// corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
				if ($this->get_attribute()->is_relation()){
					$text_column_name = RelationPath::relation_path_add($this->get_relation()->get_alias(), $this->get_text_column()->get_attribute_alias());
				} elseif ($this->get_meta_object_id() == $this->get_table()->get_meta_object_id()) {
					$text_column_name = $this->get_text_column_id();
				} else {
					unset($text_column_name);
				}
				if ($text_column_name){
					$this->set_value_text($data_sheet->get_cell_value($text_column_name, 0));
				}
			} else {
				// If the prefill data was loaded for another object, there are still multiple possibilities to prefill
				if ($data_sheet->get_meta_object()->get_id() == $this->get_relation()->get_related_object_id()){
					// If the sheet is based upon the object, that is being selected by this Combo, we can use the prefill sheet
					// values directly
					$this->set_value($data_sheet->get_cell_value($this->get_relation()->get_related_object_key_alias(), 0));
					$this->set_value_text($data_sheet->get_cell_value($this->get_text_column_id(), 0));
				} elseif ($this->get_relation()) {
					// If it is not the object selected within the combo, than we still can look for columns in the sheet, that
					// contain selectors (UIDs) of that object. This means, we need to look for data columns showing relations
					// and see if their related object is the same as the related object of the relation represented by the combo.
					foreach ($data_sheet->get_columns()->get_all() as $column){
						if ($column->get_attribute() && $column->get_attribute()->is_relation()){
							if ($column->get_attribute()->get_relation()->get_related_object()->is($this->get_relation()->get_related_object())){
								$this->set_value($column->get_values(false)[0])	;
							}							
						}
					}
				}
				// If none of the above is the case, we cannot use data from the given sheet for a prefill.
			}
		}
	}
	
	/**
	 * {@inheritDoc}
	 * 
	 * To prefill a combo, we need it's value and the corresponding text.
	 * 
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_prefill()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_prefill($data_sheet);
		
		// Do not request any prefill data, if the value is already set explicitly (e.g. a fixed value)
		if ($this->get_value()){
			return $data_sheet;
		}
		
		if ($data_sheet->get_meta_object()->is($this->get_meta_object())){
			$data_sheet->get_columns()->add_from_expression($this->get_attribute_alias());
				
			// Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
			// but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
			// set it here. In most templates, setting merely the value of the combo well make the template load the
			// corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
			if ($this->get_attribute()->is_relation()){
				$text_column_name = RelationPath::relation_path_add($this->get_relation()->get_alias(), $this->get_text_column()->get_attribute_alias());
			} elseif ($this->get_meta_object_id() == $this->get_table()->get_meta_object_id()) {
				$text_column_name = $this->get_text_column_id();
			} else {
				unset($text_column_name);
			}
			if ($text_column_name){
				$data_sheet->get_columns()->add_from_expression($text_column_name);
			}
		} elseif ($this->get_relation() && $this->get_relation()->get_related_object()->is($data_sheet->get_meta_object())){
			$data_sheet->get_columns()->add_from_expression($this->get_relation()->get_related_object_key_alias());
			$data_sheet->get_columns()->add_from_expression($this->get_text_column()->get_attribute_alias(), $this->get_text_column_id());
		} else {
			// TODO what if the prefill object is not the one at the end of the current relation?
		}
		return $data_sheet;
	}
	
	/**
	 * Since the ComboTable contains a DataTable widget, we need to return it as a child widget to allow ajax data loaders to
	 * find the table a load data for it. This does not make the ComboTable a container though!
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children(){
		return array($this->get_table());
	}
	
	public function get_paginate() {
		return $this->paginate;
	}
	
	public function set_paginate($value) {
		$this->paginate = $value;
		return $this;
	} 
	
	public function get_max_suggestions(){
		if (is_null(parent::get_max_suggestions()) && $this->get_table()){
			$this->set_max_suggestions($this->get_table()->get_paginate_default_page_size());
		}
		return parent::get_max_suggestions();
	}  
	
	public function get_table_object_alias() {
		return $this->table_object_alias;
	}
	
	public function set_table_object_alias($value) {
		$this->table_object_alias = $value;
		$this->table_object = null;
		return $this;
	}  
	
	/**
	 * Returns the meta object, that the table within the combo will show
	 * @throws UxonParserError
	 * @return object
	 */
	public function get_table_object(){
		if (!$this->table_object){
			if ($this->get_table_object_alias()){
				$this->table_object = $this->get_workbench()->model()->get_object($this->get_table_object_alias());
			} elseif($this->get_attribute()->is_relation()) {
				$this->table_object = $this->get_meta_object()->get_relation($this->get_attribute_alias())->get_related_object();
			} else {
				throw new UxonParserError('Cannot use a ComboTable for the attribute "' . $this->get_attribute_alias() . '" of object "' . $this->get_meta_object()->get_alias() . '": it is neither a relation nor is the table object specified directly!');
			}
		}
		return $this->table_object;
	}
}
?>