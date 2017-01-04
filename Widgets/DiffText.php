<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
/**
 * The DiffText widget compares two texts - an original and a new one - an shows a report highlighting the changes. This widget
 * is especially usefull since all objects in ExFace can be converted to a UXON text representation, which can be compared using
 * this widget.
 * @author Andrej Kabachnik
 *
 */
class DiffText extends AbstractWidget {
	private $left_attribute_alias = NULL;
	private $left_value = NULL;
	private $right_attribute_alias = NULL;
	private $right_value = NULL;
	
	public function get_left_value() {
		return $this->left_value;
	}
	
	public function set_left_value($value) {
		$this->left_value = $value;
		return $this;
	}
	
	public function get_right_value() {
		return $this->right_value;
	}
	
	public function set_right_value($value) {
		$this->right_value = $value;
		return $this;
	}  
	
	public function get_left_attribute_alias() {
		return $this->left_attribute_alias;
	}
	
	public function set_left_attribute_alias($value) {
		$this->left_attribute_alias = $value;
		return $this;
	}
	
	public function get_right_attribute_alias() {
		return $this->right_attribute_alias;
	}
	
	public function set_right_attribute_alias($value) {
		$this->right_attribute_alias = $value;
		return $this;
	}
	
	protected function do_prefill(DataSheetInterface $data_sheet){
		parent::do_prefill($data_sheet);
		
		// Do not do anything, if the values are already set explicitly (e.g. a fixed value)
		if ($this->get_left_value() && $this->get_right_value()){
			return;
		}
		
		if ($this->get_meta_object_id() == $data_sheet->get_meta_object()->get_id()){
			$this->set_left_value($data_sheet->get_cell_value($this->get_left_attribute_alias(), 0));
			$this->set_right_value($data_sheet->get_cell_value($this->get_right_attribute_alias(), 0));
		} else {
			throw new NotImplementedError('Prefilling DiffText with data sheets from related objects not implemented!');
		}
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
	
		if ($this->get_meta_object_id() == $data_sheet->get_meta_object()->get_id()){
			// If we are looking for attributes of the object of this widget, then just return the attribute_alias
			$data_sheet->get_columns()->add_from_expression($this->get_left_attribute_alias());
			$data_sheet->get_columns()->add_from_expression($this->get_right_attribute_alias());
		} else {
			// Otherwise we are looking for attributes relative to another object
			if ($this->get_meta_object()->find_relation($data_sheet->get_meta_object())){
				throw new NotImplementedError('Prefilling DiffText with data sheets from related objects not implemented!');
			}
		}
	
		return $data_sheet;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_prefill()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		// Do not request any prefill data, if the values are already set explicitly (e.g. a fixed value)
		if ($this->get_left_value() && $this->get_right_value()){
			return $data_sheet;
		}
		
		return $this->prepare_data_sheet_to_read($data_sheet);
	}

}
?>