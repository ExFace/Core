<?php namespace exface\Core\Contexts\Types;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\Contexts\ContextOutOfBoundsError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;

/**
 * The ObjectBasketContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 * 
 * Technically it stores a data sheet with instances for each object in the basket. Regardless of the input, this sheet will always
 * contain the default display columns.
 * 
 * @author Andrej Kabachnik
 *
 */
class ObjectBasketContext extends AbstractContext {
	private $favorites = array();
	
	public function add(DataSheetInterface $data_sheet){
		if (!$data_sheet->get_uid_column()){
			throw new ContextRuntimeError($this, 'Cannot add object "' . $this->get_input_data_sheet()->get_meta_object()->get_alias_with_namespace() . '" to object basket: missing UID-column "' . $this->get_input_data_sheet()->get_meta_object()->get_uid_alias() . '"!', '6TMQR5N');
		}
		
		$basket_data = $this->create_basket_sheet($data_sheet->get_meta_object());
		$basket_data->import_rows($data_sheet);
		if (!$basket_data->is_fresh()){
			$basket_data->add_filter_in_from_string($data_sheet->get_uid_column()->get_name(), $data_sheet->get_uid_column()->get_values(false));
			$basket_data->data_read();
		}
		
		$this->get_favorites_by_object_id($data_sheet->get_meta_object()->get_id())->add_rows($basket_data->get_rows(), true);
		return $this;
	}
	
	protected function create_basket_sheet(Object $object){
		$ds = DataSheetFactory::create_from_object($object);
		foreach ($object->get_attributes()->get_default_display_list() as $attr){
			$ds->get_columns()->add_from_attribute($attr);
		}
		return $ds;
	}
	
	protected function get_object_from_input($meta_object_or_alias_or_id){
		if ($meta_object_or_alias_or_id instanceof Object){
			$object = $meta_object_or_alias_or_id;
		} else {
			$object = $this->get_workbench()->model()->get_object($meta_object_or_alias_or_id);
		}
		return $object;
	}
	
	/**
	 * @return DataSheetInterface[]
	 */
	public function get_favorites_all(){
		return $this->favorites;
	}
	
	/**
	 * 
	 * @param string $object_id
	 * @return DataSheetInterface
	 */
	public function get_favorites_by_object_id($object_id){
		if (!$this->favorites[$object_id]){
			$this->favorites[$object_id] = DataSheetFactory::create_from_object_id_or_alias($this->get_workbench(), $object_id);
		} elseif (($this->favorites[$object_id] instanceof \stdClass) || is_array($this->favorites[$object_id])){
			$this->favorites[$object_id] = DataSheetFactory::create_from_anything($this->get_workbench(), $this->favorites[$object_id]);
		}
		return $this->favorites[$object_id];
	}
	
	/**
	 * 
	 * @param Object $object
	 * @return DataSheetInterface
	 */
	public function get_favorites_by_object(Object $object){
		return $this->get_favorites_by_object_id($object->get_id());
	}
	
	/**
	 * 
	 * @param string $alias_with_namespace
	 * @throws ContextOutOfBoundsError
	 * @return DataSheetInterface
	 */
	public function get_favorites_by_object_alias($alias_with_namespace){
		$object = $this->get_workbench()->model()->get_object_by_alias($alias_with_namespace);
		if ($object){
			return $this->get_favorites_by_object_id($object->get_id());
		} else {
			throw new ContextOutOfBoundsError($this, 'ObjectBasket requested for non-existant object alias "' . $alias_with_namespace . '"!', '6T5E5VY');
		}
	}
	
	/**
	 * The default scope of the data context is the window. Most apps will run in the context of a single window,
	 * so two windows running one app are independant in general.
	 * @see \exface\Core\Contexts\Types\AbstractContext::get_default_scope()
	 */
	public function get_default_scope(){
		return $this->get_workbench()->context()->get_scope_window();
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		foreach ((array) $uxon as $object_id => $data_uxon){
			$this->favorites[$object_id] = DataSheetFactory::create_from_uxon($this->get_workbench(), $data_uxon);
		}
	}
	
	/**
	 * The favorites context is exported to the following UXON structure:
	 * {
	 * 		object_id1: { 
	 * 			uid1: { data sheet },
	 * 			uid2: { data sheet },
	 * 			...
	 * 		}
	 * 		object_id2: ...
	 * }
	 * {@inheritDoc}
	 * @see \exface\Core\Contexts\Types\AbstractContext::export_uxon_object()
	 */
	public function export_uxon_object(){
		$uxon = $this->get_workbench()->create_uxon_object();
		foreach ($this->get_favorites_all() as $object_id => $data_sheet){
			if (!$data_sheet->is_empty()) {
				$uxon->set_property($object_id, $data_sheet->export_uxon_object());
			}
		}
		return $uxon;
	}
	
	/**
	 * 
	 * @param string $object_id
	 * @return \exface\Core\Contexts\Types\ObjectBasketContext
	 */
	public function remove_instances_for_object_id($object_id){
		unset($this->favorites[$object_id]);
		return $this;
	}
	
	/**
	 * 
	 * @param string $object_id
	 * @param string $uid
	 * @return \exface\Core\Contexts\Types\ObjectBasketContext
	 */
	public function remove_instance($object_id, $uid){
		$this->get_favorites_by_object_id($object_id)->remove_rows_by_uid($uid);
		return $this;
	}
	
}
?>