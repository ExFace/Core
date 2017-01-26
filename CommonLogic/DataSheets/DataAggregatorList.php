<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataAggregatorFactory;
use exface\Core\Interfaces\DataSheets\DataAggregatorListInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\EntityList;

class DataAggregatorList extends EntityList implements DataAggregatorListInterface {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::set_parent()
	 */
	public function set_parent($data_sheet){
		$result = parent::set_parent($data_sheet);
		foreach ($this->get_all() as $aggr){
			$aggr->set_data_sheet($data_sheet);
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		if (is_array($uxon->get_property('aggregators'))){
			$this->import_uxon_array($uxon->get_property('aggregators'));
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataAggregatorListInterface::import_uxon_array()
	 */
	public function import_uxon_array(array $uxon){
		$data_sheet = $this->get_parent();
		foreach ($uxon as $u){
			$aggr = DataAggregatorFactory::create_from_uxon($data_sheet, $u);
			$this->add($aggr);
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataAggregatorListInterface::add_from_string()
	 */
	public function add_from_string($attribute_alias){
		$data_sheet = $this->get_parent();
		$aggr = DataAggregatorFactory::create_for_data_sheet($data_sheet);
		$aggr->set_attribute_alias($attribute_alias);
		$this->add($aggr);
		return $this;
	}
	
	/**
	 * Returns the data sheet, the list belongs to.
	 * This is a better understandable alias for the inherited get_parent()
	 * @return DataSheetInterface
	 */
	public function get_data_sheet(){
		return $this->get_parent();
	}
}
?>