<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Exceptions\DataSheetException;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\InvalidArgumentException;

class DataSorter implements iCanBeConvertedToUxon, ExfaceClassInterface {
	const DIRECTION_ASC = 'ASC';
	const DIRECTION_DESC = 'DESC';
	
	private $exface = null;
	private $attribute_alias = null;
	private $direction = null;
	private $data_sheet = null;
	
	function __construct(Workbench &$exface){
		$this->exface = $exface;
	}
	
	public function get_attribute_alias() {
		return $this->attribute_alias;
	}
	
	public function set_attribute_alias($value) {
		$this->attribute_alias = $value;
		return $this;
	}
	
	public function get_direction() {
		return $this->direction;
	}
	
	public function set_direction($value) {
		if (strtoupper($value) == $this::DIRECTION_ASC){
			$this->direction = $this::DIRECTION_ASC;
		} elseif (strtoupper($value) == $this::DIRECTION_DESC){
			$this->direction = $this::DIRECTION_DESC;
		} else {
			throw new InvalidArgumentException('Invalid sort direction "' . $value . '" for a data sheet sorter: only DESC or ASC are allowed!', '6T5V9KS');
		}
		return $this;
	}
	
	public function get_data_sheet() {
		return $this->data_sheet;
	}
	
	public function set_data_sheet(DataSheetInterface &$data_sheet) {
		$this->data_sheet = $data_sheet;
		return $this;
	}
	
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		$uxon->set_property('attribute_alias', $this->get_attribute_alias());
		$uxon->set_property('direction', $this->get_direction());
		return $uxon;
	}
	
	public function import_uxon_object (UxonObject $uxon){
		$this->set_attribute_alias($uxon->get_property('attribute_alias'));
		if ($direction = $uxon->get_property('direction')){
			$this->set_direction($direction);
		}
	}
	
	public function get_workbench(){
		return $this->exface;
	}
	
	/**
	 * Returns a copy of this sorter still belonging to the same data sheet
	 * @return DataSorter
	 */
	public function copy(){
		return clone $this;
	}
	
}