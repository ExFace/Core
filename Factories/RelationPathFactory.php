<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\RelationPath;

abstract class RelationPathFactory extends AbstractFactory
{

    /**
     *
     * @param string $data_type_alias            
     * @return RelationPath
     */
    public static function createForObject(MetaObjectInterface $start_object)
    {
        return new RelationPath($start_object);
    }

    /**
     *
     * @param MetaObjectInterface $start_object            
     * @param string $relation_path_string            
     * @return RelationPath
     */
    public static function createFromString(MetaObjectInterface $start_object, $relation_path_string)
    {
        $result = self::createForObject($start_object);
        return $result->appendRelationsFromStringPath($relation_path_string);
    }
}
?>