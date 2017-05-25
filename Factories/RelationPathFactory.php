<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\CommonLogic\Model\RelationPath;

abstract class RelationPathFactory extends AbstractFactory
{

    /**
     *
     * @param string $data_type_alias            
     * @return RelationPath
     */
    public static function createForObject(Object $start_object)
    {
        return new RelationPath($start_object);
    }

    /**
     *
     * @param Object $start_object            
     * @param string $relation_path_string            
     * @return RelationPath
     */
    public static function createFromString(Object $start_object, $relation_path_string)
    {
        $result = self::createForObject($start_object);
        return $result->appendRelationsFromStringPath($relation_path_string);
    }
}
?>