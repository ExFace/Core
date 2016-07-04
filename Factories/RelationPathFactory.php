<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\RelationPath;

abstract class RelationPathFactory extends AbstractFactory {
	
	
	/**
	 * 
	 * @param string $data_type_alias
	 * @return RelationPath
	 */
	public static function create_for_object(Object $start_object){
		return new RelationPath($start_object);
	}
	
	/**
	 * 
	 * @param Object $start_object
	 * @param string $relation_path_string
	 * @return RelationPath
	 */
	public static function create_from_string(Object $start_object, $relation_path_string){
		$result = self::create_for_object($start_object);
		return $result->append_relations_from_string_path($relation_path_string);
	}
	
}
?>