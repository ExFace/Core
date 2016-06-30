<?php
namespace exface\Core\Widgets;
class CheckBox extends Input {
	
	public function transform_into_select(){
		$parent = $this->get_parent();
		$select = $this->get_page()->create_widget('InputSelect', $parent);
		$this->get_page()->remove_widget($this->get_id());
		$select->set_id($this->get_id());
		$select->set_attribute_alias($this->get_attribute_alias());
		$select->set_value($this->get_value());
		$select->set_selectable_options(array('', 1, 0), array('', 'Yes', 'No'));
		$select->set_disabled($this->is_disabled());
		$select->set_visibility($this->get_visibility());
		$select->set_caption($this->get_caption());
		return $select;
	}
	
}
?>