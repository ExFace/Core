<?php namespace exface\Core\Factories;

use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\AbstractDataType;
use exface\Core\Interfaces\NameResolverInterface;

abstract class DataTypeFactory extends AbstractNameResolverFactory {
	
	
	/**
	 * 
	 * @param NameResolverInterface $name_resolver
	 * @return AbstractDataType
	 */
	public static function create(NameResolverInterface $name_resolver){
		// TODO
		return parent::create($name_resolver);
	}
	
	/**
	 * TODO Make data types compatible to the name resolver, so they can also be added by app developers!
	 * @param exface $exface
	 * @param string $data_type_alias
	 * @return AbstractDataType
	 */
	public static function create_from_alias(Workbench $exface, $data_type_alias){
		$class = static::get_class_name_from_alias($data_type_alias);
		if (!class_exists($class)){
			$data_type_alias = ucfirst(mb_strtolower($data_type_alias));
			$class = static::get_class_name_from_alias($data_type_alias);
		}
		return new $class($exface);
	}
	
	protected static function get_class_name_from_alias($data_type_alias){
		return 'exface\\Core\\DataTypes\\' . $data_type_alias . 'DataType';
	}
	
}
?>