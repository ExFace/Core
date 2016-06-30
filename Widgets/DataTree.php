<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

class DataTree extends DataTable {
	
	protected $tree_column_id = null;
	protected $tree_parent_id_attribute_alias = null;
	protected $tree_folder_flag_attribute_alias = null;
	protected $tree_expanded = false;
	protected $tree_root_uid = null;
	
	protected $paginate = false;
	
	/**
	 * Returns the id of the column, that is supposed to display the tree
	 */
	public function get_tree_column_id() {
		return $this->tree_column_id;
	}
	
	/**
	 * 
	 * @return boolean|\exface\Core\Widgets\DataColumn
	 */
	public function get_tree_column(){
		if (!$result = $this->get_column($this->get_tree_column_id())){
			$result = $this->get_column_by_attribute_alias($this->get_tree_column_id());
		}
		return $result;
	}
	
	/**
	 * Set the id of the column, that is supposed to display the tree
	 * @param string $value
	 */
	public function set_tree_column_id($value) {
		$this->tree_column_id = $value;
	}
	
	/**
	 * Returns the alias of the attribute, that indicates, wether the node has children (= is a folder)
	 */
	public function get_tree_folder_flag_attribute_alias() {
		if (!$this->tree_folder_flag_attribute_alias){
			$flags = $this->get_meta_object()->get_attributes()->get_by_data_type_alias(EXF_DATA_TYPE_FLAG_TREE_FOLDER);
			if ($flags->count() == 1){
				$flag = $flags->get_first();
				$this->set_tree_folder_flag_attribute_alias($flag->get_alias());
			} else {
				throw new \exface\Core\Exceptions\UiWidgetException('More than one tree folder flag found for the treeGrid "' . $this->get_id() . '". Please specify "tree_folder_flag_attribute_alias" in the description of the widget!');
			}
		}
		return $this->tree_folder_flag_attribute_alias;
	}
	
	/**
	 * Sets the alias of the attribute, that indicates, wether the node has children (= is a folder)
	 * The attribute is also automatically added as a hidden column!
	 * @param string $value
	 */
	public function set_tree_folder_flag_attribute_alias($value) {
		$this->tree_folder_flag_attribute_alias = $value;
	}  
	
	/**
	 * Returns the alias of the relation to the parent object (same as the alias of the corresponding attribute).
	 * If the relation is not explicitly defined, ExFace tries to determine it automatically by searching for
	 * a recursive relation to the object itself.
	 * @throws \exface\Core\Exceptions\UiWidgetException if more than one recursive relation is found
	 */
	public function get_tree_parent_id_attribute_alias() {
		// If the parent relation is not specified explicitly, we search for a relation to the object itself
		if (!$this->tree_parent_id_attribute_alias){
			$found_one = false;
			foreach ($this->get_meta_object()->get_relations_array() as $rel){
				if ($rel->get_related_object_id() == $this->get_meta_object_id() && $rel->get_type() == 'n1'){
					if ($found_one === true){
						throw new \exface\Core\Exceptions\UiWidgetException('More than one recursive relations found for the treeGrid "' . $this->get_id() . '". Please specify "tree_parent_id_attribute_alias" in the description of the widget!');
					}
					$this->set_tree_parent_id_attribute_alias($rel->get_alias());
					$found_one = true;
				}
			}
		}
		return $this->parent_id_attribute_alias;
	}
	
	/**
	 * Sets the alias of the relation to the parent object (same as the alias of the corresponding attribute).
	 * The attribute is also automatically added as a hidden column!
	 * @param string $value
	 */
	public function set_tree_parent_id_attribute_alias($value) {
		$this->parent_id_attribute_alias = $value;
	}  
	
	public function get_tree_expanded() {
		return $this->tree_expanded;
	}
	
	public function set_tree_expanded($value) {
		$this->tree_expanded = $value;
	} 

	public function get_tree_root_uid() {
		// TODO need a method to determine the root node of a tree somehow. Perhaps query for a record with parent = null?
		if (!$this->tree_root_uid){
			$this->tree_root_uid = 1;
		}
		return $this->tree_root_uid;
	}
	
	public function set_tree_root_uid($value) {
		$this->tree_root_uid = $value;
	}  
	
	/**
	 * {@inheritDoc}
	 * 
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
	
		$data_sheet->get_columns()->add_from_expression($this->get_tree_folder_flag_attribute_alias());
		$data_sheet->get_columns()->add_from_expression($this->get_tree_parent_id_attribute_alias());
	
		return $data_sheet;
	}
}
?>