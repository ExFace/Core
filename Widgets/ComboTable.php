<?php namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveChildren;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;

/**
 * A ComboTable is similar to InputCombo, but it uses a DataTable to show the autosuggest values. 
 * 
 * Thus, the user can see more information about every suggested object. The ComboTable is very often used with relations, 
 * where the related object may have many more data, then merely it's id (which is the value of the relation attribute).
 * 
 * The DataTable for autosuggests can either be genreated automatically based on the meta object, or specified by the user via
 * UXON or even extended from any other ready-made DataTable!
 * 
 * While not every UI-framework supports such a kind of widget, there are many ways to implement the main idea of the ComboTable:
 * showing more data about a selectable object in the autosuggest. Mobile templates might use cards like in Googles material design,
 * for example.
 * 
 * ComboTables support two type of live references to other objects: in the value and in the data filters. Concider the following
 * example, where we need a product selector for an order position. We order a specific product variant, but we need a two-step
 * selector, so we can select the product first and choose one of it's variants afterwards. To do this, we need an extra product
 * selector befor the actual variant selector for our order position. The product selector does not refer to an order attribute,
 * so we declare it display_only (so it will not get included in action data). Our variant selector has a filter reference to the
 * product selector. This means, that once a product is selected, only variants of that product will be displayed. If no product
 * is selected, we can search through all product variants in the system. But what happens if we select a variant and do not
 * touch the product selector (This will actually happen every time the form is prefilled). The id-reference in the product
 * selector takes care of that: It sets the value of the selector to the product id of the selected variant. Of course, if
 * the product id does not belong to the default display attributes of the variant, we need to add it to the respective combo
 * manually: just add it next to the ~DEFAULT_DISPLAY attribute group.
 * 
 * {
 * 	"widget_type": "Form",
 * 	"object_alias": "MY.APP.ORDER_POSITION",
 * 	...
 * 	{
 * 		"widget_type": "ComboTable",
 * 		"object_alias": "MY.APP.PRODUCT",
 * 		"id": "product_selector",
 * 		"value": "=product_variant_selector!product_id",
 * 		"display_only": true
 * 	},
 * 	{
 * 		"widget_type": "ComboTable",
 * 		"attribute_alias: "PRODUCT_VARIANT"
 * 		"id": "product_variant_selector",
 * 		"table": {
 * 			"columns": [
 * 				{ "attribute_group_alias": "~DEFAULT_DISPLAY" },
 * 				{ "attribute_alias": "PRODUCT"}
 * 			],
 * 			"filters": [
 * 				{
 * 					"attribute_alias": "PRODUCT"
 * 					"value": "=product_selector!id"
 * 				}
 * 			]
 * 		}
 * 	}
 * }
 * 
 * You can add as many widgets in this chain of live references, as you wish. This way, interactive selectors can be built
 * for very complex hierarchies. If you do not want the lower hierarchy levels to be selectable before the higher levels
 * are set, make the respective fiters required (in the above example, adding "required": "true" to the PRODUCT-filter of
 * the variant selector would make this selector disabled until a product is selected).
 * 
 * Note, that if a value is changed by the user, all the referencing filters will be updated causing their widgets
 * to revalidate. This means, that changing the product in our example, will reload data for the variant selector filtered
 * by the new product. Most likely, the previously selected variant will not belong to the new product, so the variant
 * selector will be emptied automatically. Unless, of course, the new product only has one variant and 
 * autoselect_single_suggestion is true, than the value of the only variant of the new product will be automatically selected.
 * 
 * Changing or removing a value will also change/empty all referencing values. 
 * 
 * For hierarchies like the one in the above example this means, that changing a value at a certain level will change the 
 * values at higher levels and revalidate values at lower levels. Similarly, removing a value will in the middle will empty 
 * higher level selectors and revalidate lower level fields.
 * 
 * @author Andrej Kabachnik 
 * 
 */
class ComboTable extends InputCombo implements iHaveChildren { 	
	
	private $text_column_id = null;
	private $value_column_id = null;
	private $data_table = null;
	private $table_object = null;
	private $table_uxon = null;
	
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
		if (is_null($this->data_table)){
			$this->init_table();
		}
		return $this->data_table;
	}
	
	protected function init_table(){
		// This will only work if there is an attribute_alias specified
		if (!$this->get_attribute_alias()){
			throw new WidgetConfigurationError($this, 'Cannot create a DataTable for a ComboTable before an attribute_alias for the Comobo is specified!', '6T91QQ8');
			return false;
		}
		
		// Create a table widget and set those options, that may be overridden by the user in the UXON description of the Combo
		/* @var $table \exface\Core\Widgets\DataTable */
		$table = $this->get_page()->create_widget('DataTable', $this);
		$table->set_meta_object($this->get_table_object());
		$table->set_uid_column_id($this->get_value_column_id());
		$table->set_header_sort_multiple(false);
		$this->data_table = $table;
		
		// Now see if the user had already defined a table in UXON
		/* @var $table_uxon \exface\Core\CommonLogic\UxonObject */
		$table_uxon = $this->get_table_uxon();
		if (!$table_uxon->is_empty()){
			// Do not allow custom widget types
			if ($table_uxon->get_property('widget_type')){
				$table_uxon->unset_property('widget_type');
			}
			$this->data_table->import_uxon_object($table_uxon);
		}
		
		// Add default attributes
		if (!$table_uxon->has_property('columns') || count($table_uxon->get_property('columns')) == 0){
			$table->add_columns_for_default_display_attributes();
		}
		
		// Enforce those options that cannot be overridden in the table's UXON description
		$table->set_multi_select($this->get_multi_select());
		$table->set_lazy_loading($this->get_lazy_loading());
		$table->set_lazy_loading_action($this->get_lazy_loading_action());
		
		// Ensure, that special columns needed for the ComboTable are present. This must be done after $this->data_table is
		// set, because the method may use autogeneration of the text column, which needs to know about the DataTable
		$this->add_combo_columns();
		return $table;
	}
	
	protected function get_table_uxon(){
		if (is_null($this->table_uxon)){
			if ($this->get_original_uxon_object()->has_property('table')){
				$this->table_uxon = $this->get_original_uxon_object()->get_property('table');
			} else {
				$this->table_uxon = new UxonObject();
			}
		}
		return $this->table_uxon;
	}
	
	/**
	 * Defines, what the table used to display autosuggests will look like. Leave empty for an autogenerated table.
	 * 
	 * @uxon-property table
	 * @uxon-type \exface\Core\Widgets\DataTable
	 * 
	 * @param UxonObject|DataTable $widget_or_uxon_object
	 * @throws WidgetConfigurationError
	 * @throws WidgetPropertyInvalidValueError
	 * @return ComboTable
	 */
	public function set_table($widget_or_uxon_object){
		if ($widget_or_uxon_object instanceof DataTable){
			$this->data_table = $widget_or_uxon_object;
		} elseif ($widget_or_uxon_object instanceof \stdClass){
			// Do noting, the table will be initialized later, when all the other UXON properties have been processed.
			// TODO this works fine with creating widgets from UXON but will not work if a UXON object is being passed
			// programmatically - need to save the given UXON in an extra variable if we are to support this.
			if ($this->data_table){
				throw new WidgetConfigurationError($this, 'Cannot load the table-UXON of a "' . $this->get_widget_type() . '": the internal table had been already initialized!');
			}
		} elseif ($widget_or_uxon_object != '') {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid value for property "table" of "' . $this->get_widget_type() . '" given! This property only accepts UXON widget description objects or instantiated DataTable widgets.');
		}
		return $this;
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
			// If there is no text column explicitly defined, take the label attribute as text column
			if ($text_column = $this->get_table()->get_column_by_attribute_alias($this->get_text_attribute_alias())){
				// If the table already has a lable column, use it
				$this->set_text_column_id($text_column->get_id());
			} else {
				// If there is no label column yet, add it, but make it hidden, because the regular columns are what the user actually
				// wants to see - they will probably already contain the label data, but, perhaps, split into multiple columns.
				$text_column = $table->create_column_from_attribute($table_meta_object->get_attribute($this->get_text_attribute_alias()));
				$text_column->set_hidden(true);
				$table->add_column($text_column);
				$this->set_text_column_id($text_column->get_id());
			}
		}
		
		if (!$this->get_value_column_id()){
			if ($value_column = $this->get_table()->get_column_by_attribute_alias($this->get_value_attribute_alias())){
				$this->set_value_column_id($value_column->get_id());
			} else {
				$value_column = $table->create_column_from_attribute($table_meta_object->get_attribute($this->get_value_attribute_alias()), null, true);
				$table->add_column($value_column);
				$this->set_value_column_id($value_column->get_id());
			}
		}
		return $this;
	}
	
	public function get_text_column_id() { 	
		return $this->text_column_id;
	}
	
	/**
	 * Makes the displayed value (text) shown in the Combo come from a specific column of the data widget
	 *
	 * If not set, text_attribute_alias will be used, just like in a regular InputCombo
	 *
	 * @uxon-property text_column_id
	 * @uxon-type string
	 *
	 * @param string $value
	 */
	public function set_text_column_id($value) {
		$this->text_column_id = $value;
		if ($this->get_text_column()){
			$this->set_text_attribute_alias($this->get_text_column()->get_attribute_alias());
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid text_column_id "' . $value . '" specified: no matching column found in the autosuggest table!', '6TV1LBR');
		}
		return $this;
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
	
	/**
	 * Makes the internal value (mostyl invisible) of the Combo come from a specific column of the data widget
	 *
	 * If not set, value_attribute_alias will be used, just like in a regular InputCombo
	 *
	 * @uxon-property value_column_id
	 * @uxon-type string
	 *
	 * @param string $value
	 */
	public function set_value_column_id($value) {
		$this->value_column_id = $value;
		$this->get_table()->set_uid_column_id($value);
		
		if ($this->get_value_column()){
			$this->set_value_attribute_alias($this->get_value_column()->get_attribute_alias());
		} else {
			throw new WidgetPropertyInvalidValueError($this, 'Invalid value_column_id "' . $value . '" specified: no matching column found in the autosuggest table!', '6TV1LBR');
		}
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
	protected function do_prefill(DataSheetInterface $data_sheet){
		// Do not do anything, if the value is already set explicitly (e.g. a fixed value)
		if (!$this->is_prefillable()){
			return;
		}
		
		if (!$data_sheet->is_empty()){
			$this->set_prefill_data($data_sheet);
			if ($data_sheet->get_meta_object()->is($this->get_meta_object())){
				// If the prefill data is based on the same object, as the widget, simply look for the required attributes
				// in the prefill data.
				$this->set_value($data_sheet->get_cell_value($this->get_attribute_alias(), 0));
				
				// Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
				// but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
				// set it here. In most templates, setting merely the value of the combo well make the template load the
				// corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
				if ($this->get_attribute()->is_relation()){
					$text_column_name = RelationPath::relation_path_add($this->get_relation()->get_alias(), $this->get_text_column()->get_attribute_alias());
				} elseif ($this->get_meta_object()->is_exactly($this->get_table()->get_meta_object())) {
					$text_column_name = $this->get_text_column()->get_data_column_name();
				} else {
					unset($text_column_name);
				}
				if ($text_column_name){
					$this->set_value_text($data_sheet->get_cell_value($text_column_name, 0));
				}
			} else {
				// If the prefill data was loaded for another object, there are still multiple possibilities to prefill
				if ($data_sheet->get_meta_object()->is($this->get_table_object())){
					// If the sheet is based upon the object, that is being selected by this Combo, we can use the prefill sheet
					// values directly
					$this->set_value($data_sheet->get_columns()->get_by_attribute($this->get_value_attribute())->get_cell_value(0));
					$this->set_value_text($data_sheet->get_columns()->get_by_attribute($this->get_text_attribute())->get_cell_value(0));
					return;
				} elseif ($this->get_relation()){
					// If it is not the object selected within the combo, than we still can look for columns in the sheet, that
					// contain selectors (UIDs) of that object. This means, we need to look for data columns showing relations
					// and see if their related object is the same as the related object of the relation represented by the combo.
					foreach ($data_sheet->get_columns()->get_all() as $column){
						if ($column->get_attribute() && $column->get_attribute()->is_relation()){
							if ($column->get_attribute()->get_relation()->get_related_object()->is($this->get_relation()->get_related_object())){
								$this->set_values_from_array($column->get_values(false));
								return;
							}							
						}
					}
				}
				// If we are still here, that means, the above checks did not work. We still can try to use the prefill data
				// to filter the options, so just pass it to the internal data widget
				$this->get_table()->prefill($data_sheet);
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
		if (!$this->is_prefillable()){
			return $data_sheet;
		}
		
		if ($data_sheet->get_meta_object()->is($this->get_meta_object())){
			$data_sheet->get_columns()->add_from_expression($this->get_attribute_alias());
				
			// Be carefull with the value text. If the combo stands for a relation, it can be retrieved from the prefill data,
			// but if the text comes from an unrelated object, it cannot be part of the prefill data and thus we can not
			// set it here. In most templates, setting merely the value of the combo will make the template load the
			// corresponding text by itself (e.g. via lazy loading), so it is not a real problem.
			if ($this->get_attribute() && $this->get_attribute()->is_relation()){
				$text_column_name = RelationPath::relation_path_add($this->get_relation()->get_alias(), $this->get_text_column()->get_attribute_alias());
			} elseif ($this->get_meta_object()->is_exactly($this->get_table()->get_meta_object())) {
				$text_column_name = $this->get_text_column()->get_data_column_name();
			} else {
				unset($text_column_name);
			}
			if ($text_column_name){
				$data_sheet->get_columns()->add_from_expression($text_column_name, $this->get_text_column()->get_data_column_name());
			}
		} elseif ($this->get_relation() && $this->get_relation()->get_related_object()->is($data_sheet->get_meta_object())){
			$data_sheet->get_columns()->add_from_expression($this->get_relation()->get_related_object_key_alias());
			foreach ($this->get_table()->get_columns() as $col) {
				$data_sheet->get_columns()->add_from_expression($col->get_attribute_alias(), $col->get_data_column_name());
			}
			//$data_sheet->get_columns()->add_from_expression($this->get_text_column()->get_attribute_alias(), $this->get_text_column()->get_data_column_name());
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
	
	public function get_max_suggestions(){
		if (is_null(parent::get_max_suggestions()) && $this->get_table()){
			$this->set_max_suggestions($this->get_table()->get_paginate_default_page_size());
		}
		return parent::get_max_suggestions();
	}  
	
	public function get_table_object_alias() {
		return $this->get_options_object_alias();
	}
	
	/**
	 * Makes the autosuggest-table use a different meta object than the input.
	 * 
	 * Use with case! Using a different object normally requires custom value_column_id and text_column_id.
	 * 
	 * @uxon-property table_object_alias
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return \exface\Core\Widgets\ComboTable
	 */
	public function set_table_object_alias($value) {
		$this->table_object = null;
		return $this->set_options_object_alias($value);
	}  
	
	/**
	 * Returns the meta object, that the table within the combo will show
	 * @throws WidgetConfigurationError
	 * @return object
	 */
	public function get_table_object(){
		if (!$this->has_custom_options_object()) {
			if ($this->get_attribute()->is_relation()){
				$this->set_options_object($this->get_meta_object()->get_relation($this->get_attribute_alias())->get_related_object());
			} 
		}
		return $this->get_options_object();
	}
	
	/**
	 * Sets an optional array of filter-objects to be used when fetching autosugest data from a data source.
	 *
	 * For example, if we have a ComboTable for customer ids, but we only wish to show customers of a certain
	 * class (assuming every custer hase a relation "CUSOMTER_CLASS"), we would need the following ComboTable:
	 * {
	 * 	"options_object_alias": "my.app.CUSTOMER",
	 * 	"filters":
	 * 	[
	 * 		{"attribute_alias": "CUSTOMER_CLASS__ID", "value": "VIP", "comparator": "="}
	 * 	]
	 * }
	 * 
	 * We can even use widget references to get the filters. Imagine, the ComboTable for customers above is
	 * placed in a form, where the customer class can be selected explicitly in another ComboTable or a InputSelect
	 * with the id "customer_class_selector".
	 * {
	 * 	"options_object_alias": "my.app.CUSTOMER",
	 * 	"filters":
	 * 	[
	 * 		{"attribute_alias": "CUSTOMER_CLASS__ID", "value": "=customer_class_selector!ID"}
	 * 	]
	 * }
	 *
	 * @uxon-property filters
	 * @uxon-type \exface\Core\CommonLogic\Model\Condition
	 *
	 * @param Condition[]|UxonObject[] $conditions_or_uxon_objects
	 * @return \exface\Core\Widgets\InputSelect
	 */
	public function set_filters(array $conditions_or_uxon_objects){
		if (!$this->get_table_uxon()->has_property('filters')){
			$this->get_table_uxon()->set_property('filters', array());
		}
		
		foreach ($conditions_or_uxon_objects as $condition_or_uxon_object){
			if ($condition_or_uxon_object instanceof Condition){
				// TODO
			} elseif ($condition_or_uxon_object instanceof \stdClass) {
				$this->get_table_uxon()->set_property('filters', array_merge($this->get_table_uxon()->get_property('filters'), array($condition_or_uxon_object)));
			}
		}
		return $this;
	}
}
?>