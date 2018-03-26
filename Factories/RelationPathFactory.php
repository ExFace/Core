<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Model\RelationPath;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;

abstract class RelationPathFactory extends AbstractStaticFactory
{

    /**
     *
     * @param string $data_type_alias            
     * @return MetaRelationPathInterface
     */
    public static function createForObject(MetaObjectInterface $start_object)
    {
        return new RelationPath($start_object);
    }

    /**
     *
     * @param MetaObjectInterface $start_object            
     * @param string $relation_path_string            
     * @return MetaRelationPathInterface
     */
    public static function createFromString(MetaObjectInterface $start_object, $relation_path_string)
    {
        $result = self::createForObject($start_object);
        return $result->appendRelationsFromStringPath($relation_path_string);
    }
}
?>