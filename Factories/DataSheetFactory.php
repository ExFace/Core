<?php namespace exface\Core\Factories;

use exface\Core\UxonObject;
use exface\Core\Model\Object;
use exface\exface;
use exface\Core\DataSheet;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\FactoryError;

abstract class DataSheetFactory extends AbstractUxonFactory {
	
	/**
	 * Creates a data sheet for a give object. The object can be passed directly or specified by it's fully qualified alias (with namespace!)
	 * @param exface $exface
	 * @param Object|string $meta_object_or_alias
	 * @return DataSheetInterface
	 */
	public static function create_from_object_id_or_alias(exface &$exface, $meta_object_or_alias = null){
		if ($meta_object_or_alias instanceof Object){
			$meta_object = $meta_object_or_alias;
		} else {
			$meta_object = $exface->model()->get_object($meta_object_or_alias);
		}
		return static::create_from_object($meta_object);
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @return DataSheetInterface
	 */
	public static function create_empty(exface &$exface){
		return static::create_from_object_id_or_alias($exface);
	}
	
	/**
	 * 
	 * @param Object $meta_object
	 * @return DataSheetInterface
	 */
	public static function create_from_object(Object $meta_object){
		$data_sheet = new DataSheet($meta_object);
		return $data_sheet;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param UxonObject $uxon
	 * @return DataSheetInterface
	 */
	public static function create_from_uxon(exface &$exface, UxonObject $uxon){
		$object_alias = $uxon->get_property('object_alias') ? $uxon->get_property('object_alias') : $uxon->get_property('meta_object_alias');
		$meta_object = $exface->model()->get_object($object_alias ? $object_alias : $uxon->meta_object_id);
		$data_sheet = self::create_from_object($meta_object);
		$data_sheet->import_uxon_object($uxon);
		return $data_sheet;
	}
	
	/**
	 * 
	 * @param exface $exface
	 * @param unknown $data_sheet_or_uxon
	 * @throws FactoryError
	 * @return DataSheetInterface
	 */
	public static function create_from_anything(exface &$exface, $data_sheet_or_uxon){
		if ($data_sheet_or_uxon instanceof DataSheetInterface){
			return $data_sheet_or_uxon;
		} elseif ($data_sheet_or_uxon instanceof \stdClass){
			return static::create_from_stdClass($exface, $data_sheet_or_uxon);
		} elseif (!is_object($data_sheet_or_uxon)) {
			return static::create_from_uxon($exface, UxonObject::from_json($data_sheet_or_uxon));
		} else {
			throw new FactoryError('Cannot create data sheet from "' . get_class($data_sheet_or_uxon) . '"!');
		}
	}
	
}
?>