<?php namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Factories\DataSorterFactory;
use exface\Core\Interfaces\DataSheets\DataSorterListInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Exceptions\ErrorExceptionInterface;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
class DataSorterList extends EntityList implements DataSorterListInterface {
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::set_parent()
	 */
	public function set_parent($data_sheet){
		$result = parent::set_parent($data_sheet);
		foreach ($this->get_all() as $sorter){
			$sorter->set_data_sheet($data_sheet);
		}
		return $result;
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\CommonLogic\EntityList::import_uxon_object()
	 */
	public function import_uxon_object(UxonObject $uxon){
		if (is_array($uxon->get_property('sorters'))){
			$this->import_uxon_array($uxon->get_property('sorters'));
		}
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see \exface\Core\Interfaces\DataSheets\DataSorterListInterface::import_uxon_array()
	 */
	public function import_uxon_array(array $uxon){
		$data_sheet = $this->get_parent();
		foreach ($uxon as $u){
			$aggr = DataSorterFactory::create_from_uxon($data_sheet, $u);
			$this->add($aggr);
		}
	}
	
	/**
	 * Returns the data sheet, the list belongs to. 
	 * This is a better understandable alias for the inherited get_parent()
	 * @return DataSheetInterface
	 */
	public function get_data_sheet(){
		return $this->get_parent();
	}
	
	/**
	 * Adds a new sorter to the the list, creating it either from a column_id or an attribute alias.
	 *
	 * TODO The possiblity to sort over a column name (QTY_SUM) and the corresponding expression (QTY:SUM) causes trouble. The solution here is quite dirty.
	 * A better way would probably be to take care of the different expressions before adding the sorter to the data sheet or makeing two separate methods.
	 * 
	 * @param string $attribute_alias_or_column_id
	 * @param string $direction
	 * @return DataSorterListInterface
	 */
	public function add_from_string($attribute_alias_or_column_id, $direction = 'ASC'){
		// If the sorter is not just a simple attribute, try to find the attribute in the column corresponding to the sorter
		// This helps if the id of the column is passed instead of the expression.
		$data_sheet = $this->get_data_sheet();
		try {
			$attr = $data_sheet->get_meta_object()->get_attribute($attribute_alias_or_column_id);
		} catch (ErrorExceptionInterface $e){
			// No need to do anything here, because $attr will automatically remain NULL
		}
	
		if (!$attr){
			if ($col = $data_sheet->get_column($attribute_alias_or_column_id)){
				if ($col->get_expression_obj()->is_meta_attribute()){
					$attribute_alias = $col->get_expression_obj()->to_string();
				} else {
					$attrs = $col->get_expression_obj()->get_required_attributes();
					if (count($attrs) > 0){
						$attribute_alias = reset($attrs);
					}
				}
			}
		} else {
			$attribute_alias = $attribute_alias_or_column_id;
		}
		
		if (!$attribute_alias){
			throw new DataSheetStructureError($this->get_data_sheet(), 'Cannot add a sorter over "' . $attribute_alias_or_column_id . '" to data sheet with object "' . $this->get_data_sheet()->get_meta_object()->get_alias_with_namespace() . '": no matching attribute could be found!', '6UQBX9K');
		}
		
		$sorter = DataSorterFactory::create_for_data_sheet($data_sheet);
		$sorter->set_attribute_alias($attribute_alias);
		$sorter->set_direction($direction);
		$this->add($sorter);
		
		return $this;
	}

}
?>