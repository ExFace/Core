<?php namespace exface\Core\Events;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Data sheet event names consist of the qualified alias of the base meta object followed by "DataSheet" and the respective event type:
 * e.g. exface.Core.Object.DataSheet.UpdateData.Before, etc.
 * @author aka
 *
 */
class DataSheetEvent extends ExFaceEvent {
	private $data_sheet = null;
	
	/**
	 * @return DataSheetInterface
	 */
	public function get_data_sheet() {
		return $this->data_sheet;
	}
	
	/**
	 * 
	 * @param DataSheetInterface $value
	 * @return DataSheetEvent
	 */
	public function set_data_sheet(DataSheetInterface &$value) {
		$this->data_sheet = $value;
		return $this;
	}  
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Events\ExFaceEvent::get_namespace()
	 */
	public function get_namespace(){
		return $this->get_data_sheet()->get_meta_object()->get_alias_with_namespace() . NameResolver::NAMESPACE_SEPARATOR . 'DataSheet';
	}
}