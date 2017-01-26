<?php
namespace exface\Core\Widgets;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
/**
 * A Tab is a special panel to be used within the tabs widget
 * @author Andrej Kabachnik
 *
 */
class Tab extends Panel {
	private $badge_attribute_alias;
	private $badge_value;
	
	public function get_badge_attribute_alias() {
		return $this->badge_attribute_alias;
	}
	
	public function set_badge_attribute_alias($value) {
		$this->badge_attribute_alias = $value;
		return $this;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_read()
	 */
	public function prepare_data_sheet_to_read(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_read($data_sheet);
	
		$data_sheet->get_columns()->add_from_expression($this->get_badge_attribute_alias());
	
		return $data_sheet;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \exface\Core\Widgets\AbstractWidget::prepare_data_sheet_to_prefill()
	 */
	public function prepare_data_sheet_to_prefill(DataSheetInterface $data_sheet = null){
		$data_sheet = parent::prepare_data_sheet_to_prefill($data_sheet);
	
		$data_sheet->get_columns()->add_from_expression($this->get_badge_attribute_alias());
	
		return $data_sheet;
	}
	
	public function get_badge_value() {
		return $this->badge_value;
	}
	
	public function set_badge_value($value) {
		$this->badge_value = $value;
		return $this;
	}
	
	protected function do_prefill(DataSheetInterface $data_sheet){
		parent::do_prefill($data_sheet);
		if ($this->get_badge_attribute_alias()){
			if ($this->get_meta_object_id() == $data_sheet->get_meta_object()->get_id()){
				$this->set_badge_value($data_sheet->get_cell_value($this->get_badge_attribute_alias(), 0));
			} else {
				throw new NotImplementedError('Prefilling Tab badges with data sheets from related objects not implemented!');
			}
		}
		return $this;
	}
}
?>