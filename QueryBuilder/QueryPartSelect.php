<?php
namespace exface\Core\QueryBuilder;
class QueryPartSelect extends QueryPartAttribute {
	public function is_valid(){
		if ($this->get_attribute()->get_data_address() != '') return true;
		return false;
	}
}
?>