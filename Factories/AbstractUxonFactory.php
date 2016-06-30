<?php namespace exface\Core\Factories;

use exface\Core\UxonObject;
use exface\exface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Exceptions\FactoryError;

abstract class AbstractUxonFactory extends AbstractFactory {
	
	/**
	 * Creates an object from a standard class (e.g. the result of json_decode())
	 * @param exface $exface
	 * @param \stdClass $json_object
	 */
	public static function create_from_stdClass(exface &$exface, \stdClass $json_object){
		if ($json_object instanceof UxonObject){
			$uxon = $json_object;
		} else {
			$uxon = UxonObject::from_stdClass($json_object);
		}
		return static::create_from_uxon($exface, $uxon);
	}
	
	/**
	 * Creates a business object from it's UXON description. If the business object implements iCanBeConvertedToUxon, this method
	 * will work automatically. Otherwise it needs to be overridden in the specific factory.
	 * @param exface $exface
	 * @param UxonObject $uxon
	 * @throws FactoryError
	 */
	public static function create_from_uxon(exface &$exface, UxonObject $uxon){
		$result = static::create_empty($exface);
		if ($result instanceof iCanBeConvertedToUxon){
			$result->import_uxon_object($uxon);
		} else {
			throw new FactoryError('Cannot create "' . get_class($result) . '" from UXON automatically! It should either implement the interface iCanBeConvertedToUxon or the create_from_uxon() method must be overridden in "' . get_class(self) . '"!');
		}
		return $result;
	}
	
}
?>