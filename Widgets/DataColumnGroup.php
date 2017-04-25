<?php namespace exface\Core\Widgets;

use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iHaveColumns;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Exceptions\Widgets\WidgetHasNoUidColumnError;
use exface\Core\Exceptions\Model\MetaObjectHasNoUidAttributeError;

/**
 * The DataColumnGroup is a group of columns in a data widget from one side and at the same time a full featured data widget on the other.
 * This duality makes it possible to define custom filters and even aggregators for each column group. If not done so, it will just be
 * a group of columns with it's own caption, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class DataColumnGroup extends AbstractWidget implements iHaveColumns {
	// children widgets
	/** @var DataColumn[] */
	private $columns = array();
	private $uid_column_id = null;
	
	public function add_column(DataColumn $column){
		$column->set_meta_object_id($this->get_meta_object_id());
		if ($column->is_editable()){
			$this->get_parent()->set_editable(true);
			$this->get_parent()->add_columns_for_system_attributes();
			// If an attribute of a related object should be editable, we need it's system attributes as columns -
			// that is, at least a column with the UID of the related object, but maybe also some columns needed for
			// the behaviors of the related object
			if ($column->get_attribute() && $rel_path = $column->get_attribute()->get_relation_path()->to_string()){
				$rel = $this->get_meta_object()->get_relation($rel_path);
				if ($rel->get_type() == 'n1'){
					$this->get_parent()->add_columns_for_system_attributes($rel_path);
				} elseif ($rel->get_type() == '1n'){
					// TODO Concatennate UIDs here?
				} elseif ($rel->get_type() == '11'){
					// TODO
				}
			}
		}
		$this->columns[] = $column;
		return $this;
	}
	
	/**
	 * Creates a DataColumn from a meta attribute. For relations the column will automatically show the label of the related object
	 * @param attribute $attribute
	 * @return \exface\Core\Widgets\DataColumn
	 */
	function create_column_from_attribute(Attribute $attribute, $caption=null, $hidden=null){
		if ($attribute->is_relation()){
			$attribute = $this->get_meta_object()->get_attribute(RelationPath::relation_path_add($attribute->get_alias(), $this->get_meta_object()->get_related_object($attribute->get_alias())->get_label_alias()));
		}
		
		$c = $this->get_page()->create_widget('DataColumn', $this);
		$c->set_attribute_alias($attribute->get_alias_with_relation_path());
		
		if (!is_null($hidden)) {
			$c->set_hidden($hidden);
		}
		
		if (!is_null($caption)) {
			$c->set_caption($caption);
		}
		
		return $c;
	}
	
	/**
	 * Returns the id of the column holding the UID of each row. By default it is the column with the UID attribute of
	 * the meta object displayed in by the data widget, but this can be changed in the UXON description if required.
	 * @return string
	 */
	function get_uid_column_id(){
		// If there is no UID column defined yet, try to generate one automatically
		if (is_null($this->uid_column_id)){
			try {
				if (!$col = $this->get_column_by_attribute_alias($this->get_meta_object()->get_uid_attribute()->get_alias_with_relation_path())){
					$col = $this->create_column_from_attribute($this->get_meta_object()->get_uid_attribute(), null, true);
					$this->add_column($col);
				}
				$this->uid_column_id = $col->get_id();
			} catch (MetaObjectHasNoUidAttributeError $e){
				// Do nothing. Depending on what the user wants to do with the column group, it might work without
				// a UID column. If not, an error will be generated elsewhere.
			}
		}
		return $this->uid_column_id;
	}
	
	/**
	 * Sets the id of the column to be used as UID for each data row in this column group.
	 * 
	 * This can be usefull if the column group is based on a meta object not directly related to
	 * the the object of the parent Data widet. In this case, you can specify which column of the
	 * group to use, to join rows to the main data.
	 * 
	 * @uxon-property uid_column_id
	 * @uxon-type string
	 * 
	 * @param string $value
	 * @return DataColumnGroup
	 */
	public function set_uid_column_id($value){
		$this->uid_column_id = $value;
		return $this;
	}
	
	public function get_uid_column(){
		if (!$this->get_uid_column_id()){
			throw new WidgetHasNoUidColumnError($this, 'No UID column found in DataColumnGroup: either set uid_column_id for the column group explicitly or give the object "' . $this->get_meta_object()->get_alias_with_namespace() . '" a UID attribute!');
		}
		if (!$col = $this->get_column($this->get_uid_column_id())){
			$col = $this->get_parent()->get_column($this->get_uid_column_id());
		}
		return $col;
	}
	
	/**
	 * Returns TRUE if this column group has a UID column or FALSE otherwise.
	 * @return boolean
	 */
	public function has_uid_column(){
		try {
			$this->get_uid_column();
		} catch (WidgetHasNoUidColumnError $e){
			return false;
		}
		return true;
	}
	
	/**
	 * Returns TRUE if this column group is the main column group of the parent widget
	 * @return boolean
	 */
	public function is_main_column_group(){
		if ($this->get_parent()->get_column_group_main() === $this){
			return true;
		} else {
			return false;
		}
	}
	
	public function is_empty(){
		if (count($this->columns) > 0){
			return false;
		} else {
			return true;
		}
	}
	
	public function get_columns(){
		return $this->columns;
	}
	
	/**
	 * Returns the data column matching the given id.
	 * @param unknown $column_id
	 * @return \exface\Core\Widgets\DataColumn|boolean
	 */
	public function get_column($column_id, $use_data_column_names_as_fallback = true){
		foreach ($this->get_columns() as $col){
			if ($col->get_id() === $column_id){
				return $col;
			}
		}
		if ($use_data_column_names_as_fallback){
			return $this->get_column_by_data_column_name($column_id);
		}
		return false;
	}
	
	function get_column_by_attribute_alias($alias_with_relation_path){
		foreach ($this->get_columns() as $col){
			if ($col->get_attribute_alias() === $alias_with_relation_path){
				return $col;
			}
		}
		return false;
	}
	
	function get_column_by_data_column_name($data_sheet_column_name){
		foreach ($this->get_columns() as $col){
			if ($col->get_data_column_name() === $data_sheet_column_name){
				return $col;
			}
		}
		return false;
	}
	
	/**
	 * Defines the DataColumns within this group: an array of respecitve UXON objects.
	 * 
	 * @uxon-property columns
	 * @uxon-type DataColumn[]
	 * 
	 * @see \exface\Core\Interfaces\Widgets\iHaveColumns::set_columns()
	 */
	public function set_columns(array $columns) {
		foreach ($columns as $c) {
			$caption = null;
			if ($c->attribute_group_alias){
				foreach ($this->get_meta_object()->get_attribute_group($c->attribute_group_alias)->get_attributes() as $attr){
					$this->add_column($this->create_column_from_attribute($attr));
				}
				continue;
			}
			// preset some column properties based on meta attributes

			// Set the caption to the attribute name or the relation name, if the attribute is the label of a related object.
			// This preset caption will get overwritten by one specified in UXON once the UXON object is overloaded
			if (!$c->caption && $this->get_meta_object()->has_attribute($c->attribute_alias)){
				$attr = $this->get_meta_object()->get_attribute($c->attribute_alias);
				if ($attr->is_label() && $attr->get_relation_path()->to_string()){
					$caption = $this->get_meta_object()->get_relation($attr->get_relation_path()->to_string())->get_name();
				} else {
					$caption = $attr->get_name();
				}
			}

			// Create the column
			$column_type = $c->widget_type ? $c->widget_type : 'DataColumn';
			$column = $this->get_page()->create_widget($column_type, $this);
			$column->set_caption($caption);
			$column->import_uxon_object($c);

			// Add the column to the widget
			$this->add_column($column);
		}
		return $this;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::get_children()
	 */
	public function get_children(){
		$children = $this->get_columns();
		return $children;
	}
	
	/**
	 * Returns the number of columns in this group (including hidden columns!)
	 * @return integer
	 */
	public function count_columns(){
		return count($this->get_columns());
	}
	
	/**
	 * Returns the number of visible columns in this group
	 * @return integer
	 */
	public function count_columns_visible(){
		$result = 0;
		foreach ($this->get_columns() as $column){
			if (!$column->is_hidden()){
				$result++;
			}
		}
		return $result;
	}
}
?>