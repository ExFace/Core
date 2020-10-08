<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeCopied;

/**
 * The relation path object holds all relations needed to reach the end object from the start object.
 * If attributes
 * or relations are fetched from an object, that they don't directly belong to, the relation path part of their
 * qualified alias will be stored here (not the alias itself!): the attribute ORDER->ORDER_TYPE->CODE fetched
 * from the object ORDER_POSITION will have an attribute path of two relations: ORDER and ORDER_TYPE. It will
 * not contain any information about the attribute CODE itself, but will be stored in this attribute.
 *
 * @author Andrej Kabachnik
 *
 */
interface MetaRelationPathInterface extends \IteratorAggregate, iCanBeCopied
{
    /**
     * 
     * @param MetaObjectInterface $start_object
     */
    public function __construct(MetaObjectInterface $start_object);
    
    /**
     * Returns all relations from this path as an array
     *
     * @return MetaRelationInterface[]
     */
    public function getRelations();
    
    /**
     * Adds the given relation to the right end of the path
     *
     * @param MetaRelationInterface $relation
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function appendRelation(MetaRelationInterface $relation);
    
    /**
     * Adds the given relation to the left end of the path
     *
     * @param MetaRelationInterface $relation
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function prependRelation(MetaRelationInterface $relation);
    
    /**
     * Adds all relations from the given path string to the left end of the path.
     * Use with caution as ambiguos
     * reverse relation may cause trouble. Prefer append_relation() insted, because it always explicitly specifies
     * each relation!
     *
     * @param string $relation_path_string
     * @return MetaRelationPathInterface
     */
    public function appendRelationsFromStringPath($relation_path_string);
    
    public function toString();
    
    public function getWorkbench();
    
    /**
     * @return MetaObjectInterface
     */
    public function getStartObject();
    
    /**
     * Returns the last object in the relation path (the related object of the last relation)
     *
     * @return MetaObjectInterface
     */
    public function getEndObject();
    
    /**
     * Returns the nth relation in the path (starting with 0 for the first relation).
     *
     * @param integer $sequence_number
     * @return MetaRelationInterface
     */
    public function getRelation($index);
    
    /**
     * Returns the first relation of the path or NULL if the path is empty
     *
     * @return MetaRelationInterface
     */
    public function getRelationFirst();
    
    /**
     * Returns the last relation of the path or NULL if the path is empty
     *
     * @return MetaRelationInterface
     */
    public function getRelationLast();
    
    public function countRelations();
    
    /**
     * Returns a new relation path appending the given path to the current one
     *
     * @param MetaRelationPathInterface $path_to_append
     * @return MetaRelationPathInterface
     */
    public function combine(MetaRelationPathInterface $path_to_append);
    
    /**
     * Returns a relation path, that $length of relations from this path starting with the relation at $start_index.
     *
     * Examples:
     * ORDER__CUSTOMER__ADDRESS__TYPE__LABEL::subpath(2) = ADDRESS__TYPE__LABEL
     * ORDER__CUSTOMER__ADDRESS__TYPE__LABEL::subpath(0,2) = ORDER__CUSTOMER
     * ORDER__CUSTOMER__ADDRESS__TYPE__LABEL::subpath(0,-2) = 'ORDER__CUSTOMER__ADDRESS'
     * ORDER__CUSTOMER__ADDRESS__TYPE__LABEL::subpath(-2) = 'TYPE__LABEL'
     *
     * @param string $relation_path
     * @param string $cut_before_alias
     * @return MetaRelationPathInterface
     */
    public function getSubpath($start_index = 0, $length = null);
    
    /**
     * Returns the numeric index of the first occurrence of the given relation in the path or FALSE if the relation was not found.
     *
     * @param MetaRelationInterface $relation
     * @param boolean $exact_match
     * @return integer|boolean
     */
    public function getIndexOf(MetaRelationInterface $relation, $exact_match = false);
    
    /**
     * Returns TRUE if the path does not contain any relation and FALSE otherwise
     */
    public function isEmpty();
    
    /**
     * Returns a new relation path from the end object of this path to it's start object.
     * 
     * TODO Make the staic method relation_path_reverse protected or remove it once all calls are replaced
     * by this method!
     *
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function reverse();
    
    
    public function getIterator();
    
    /**
     * @return string
     */
    public static function getRelationSeparator();
    
    /**
     * Returns an attribute of the end object specified by it's attribute alias, but with a relation path relative to the start object.
     * E.g. calling getAttributeOfEndObject('POSITION_NO') on the relation path ORDER<-POSITION will return ORDER__POSITION__POSITION_NO
     *
     * @param string $attribute_alias
     * @return MetaAttributeInterface
     */
    public function getAttributeOfEndObject($attribute_alias);
    
    /**
     * 
     * @return bool
     */
    public function containsReverseRelations() : bool;
}

