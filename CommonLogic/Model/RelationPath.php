<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\OutOfRangeException;

/**
 * The relation path object holds all relations needed to reach the end object from the start object.
 * If attributes
 * or relations are fetched from an object, that they don't directly belong to, the relation path part of their
 * qualified alias will be stored here (not the alias itself!): the attribute ORDER->ORDER_TYPE->CODE fetched
 * from the object ORDER_POSITION will have an attribute path of two relations: ORDER and ORDER_TYPE. It will
 * not contain any information about the attribute CODE itself, but will be stored in this attribute.
 *
 * TODO Moving from string relation paths to this object introduces lot's auf new possibilities. Old code should
 * be rewritten to use them: specificly query builders and widgets, relying on relation paths! After this, the
 * static methods relation_path_xxx can be removed or made protected!
 *
 * @author Andrej Kabachnik
 *        
 */
class RelationPath implements \IteratorAggregate
{

    const RELATION_SEPARATOR = '__';

    /* Properties to be dublicated on copy() */
    private $relations = array();

    /* Properties NOT to be dublicated on copy() */
    private $start_object = null;

    public function __construct(Object $start_object)
    {
        $this->start_object = $start_object;
    }

    /**
     * Returns all relations from this path as an array
     *
     * @return relation[]
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * Adds the given relation to the right end of the path
     *
     * @param Relation $relation            
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function appendRelation(Relation $relation)
    {
        $this->relations[] = $relation;
        return $this;
    }

    /**
     * Adds the given relation to the left end of the path
     *
     * @param Relation $relation            
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function prependRelation(Relation $relation)
    {
        array_unshift($this->relations, $relation);
        return $this;
    }

    /**
     * Adds all relations from the given path string to the left end of the path.
     * Use with caution as ambiguos
     * reverse relation may cause trouble. Prefer append_relation() insted, because it always explicitly specifies
     * each relation!
     *
     * @param string $relation_path_string            
     * @return RelationPath
     */
    public function appendRelationsFromStringPath($relation_path_string)
    {
        $first_rel = self::getFirstRelationFromStringPath($relation_path_string);
        try {
            $rel = $this->getEndObject()->getRelation($first_rel);
            if (is_array($rel)) {
                // TODO what if it is a ambiguous reverse relation?
            } else {
                $this->appendRelation($rel);
            }
        } catch (MetaRelationNotFoundError $e) {
            // Do nothing, if it is not a relation (it's probably an attribute than)
            // IDEA Maybe check, to see if it really is an attribute and throw an error otherwise???
            $rel = false;
        }
        
        if ($first_rel != $relation_path_string) {
            return $this->appendRelationsFromStringPath(substr($relation_path_string, (strlen($first_rel) + strlen(self::RELATION_SEPARATOR))));
        }
        
        return $this;
    }

    protected static function getFirstRelationFromStringPath($string_relation_path)
    {
        return explode(self::RELATION_SEPARATOR, $string_relation_path, 2)[0];
    }

    public function toString()
    {
        $path = '';
        foreach ($this->getRelations() as $rel) {
            $path = $path . self::RELATION_SEPARATOR . $rel->getAlias();
        }
        $path = trim($path, self::RELATION_SEPARATOR);
        return $path;
    }

    public function getWorkbench()
    {
        return $this->getStartObject()->getModel()->getWorkbench();
    }

    public function getStartObject()
    {
        return $this->start_object;
    }

    /**
     * Returns the last object in the relation path (the related object of the last relation)
     *
     * @return Object
     */
    public function getEndObject()
    {
        if (! $this->isEmpty()) {
            return $this->getRelationLast()->getRelatedObject();
        } else {
            return $this->getStartObject();
        }
    }

    /**
     * Returns the nth relation in the path (starting with 0 for the first relation).
     *
     * @param integer $sequence_number            
     * @return Relation
     */
    public function getRelation($index)
    {
        return $this->getRelations()[$index];
    }

    /**
     * Returns the first relation of the path or NULL if the path is empty
     *
     * @return Relation
     */
    public function getRelationFirst()
    {
        return $this->getRelation(0);
    }

    /**
     * Returns the last relation of the path or NULL if the path is empty
     *
     * @return Relation
     */
    public function getRelationLast()
    {
        return $this->getRelation($this->countRelations() - 1);
    }

    public function countRelations()
    {
        return count($this->getRelations());
    }

    /**
     * DEPRECATED! Use RelationPathFactory::crate_from_string_path() instead!
     * TODO make protected or remove
     * Checks if the given alias includes a relation path and returns an array with relations
     *
     * @param string $col            
     * @param int $depth            
     *
     * @return array|false
     */
    public static function relationPathParse($alias, $depth = 0)
    {
        $depth = intval($depth);
        
        $sep = self::RELATION_SEPARATOR;
        if (strpos($alias, $sep) === false) {
            return false;
        } else {
            if ($depth) {
                return explode($sep, $alias, $depth + 1);
            } else {
                return explode($sep, $alias);
            }
        }
    }

    /**
     * DEPRECATED! Use combine() instead!
     *
     * @param unknown $relation_alias_1            
     * @param unknown $relation_alias_2            
     */
    public static function relationPathAdd($relation_alias_1, $relation_alias_2)
    {
        $output = '';
        if ($relation_alias_1) {
            $output = $relation_alias_1;
        }
        if ($relation_alias_2) {
            $output = $output ? $output . self::RELATION_SEPARATOR . $relation_alias_2 : $relation_alias_2;
        }
        
        return $output;
    }

    /**
     * Returns a new relation path appending the given path to the current one
     *
     * @param RelationPath $path_to_append            
     * @return RelationPath
     */
    public function combine(RelationPath $path_to_append)
    {
        $result = $this->copy();
        foreach ($path_to_append as $relation) {
            $result->appendRelation($relation);
        }
        return $result;
    }

    /**
     * DEPRECATED! Use reverse() instead!
     * Reverses a given relation path relative to a given object: POSITION->PRODUCT relative to an ORDER will become POSITION->ORDER and thus can now be
     * used in the context of a PRODUCT.
     *
     * @param string $relation_path            
     * @param object $meta_object            
     * @return string
     */
    public static function relationPathReverse($relation_path, Object $meta_object)
    {
        $output = '';
        // Parse the relation path to get an array of relation aliases
        $relations = self::relationPathParse($relation_path);
        // If the path is jus a single regular relation, parsing it will return FALSE, because it is also an attribute of the object
        // In this case, jus take the relation path as the alias
        if (! $relations && $meta_object->getAttribute($relation_path)->isRelation()) {
            $relations = array(
                $relation_path
            );
        }
        
        // Loop through the relation alias and find out the alias of each of the from the point of view of the next related object
        $current_object = $meta_object;
        $reverse_aliases = array();
        foreach ($relations as $rel_alias) {
            /* @var $rel \exface\Core\CommonLogic\Model\relation */
            try {
                $rel = $current_object->getRelation($rel_alias);
            } catch (MetaRelationNotFoundError $e) {
                // Skip non-relations (will ignore regular attributes at the end of the chain)
                continue;
            }
            
            $reverse_aliases[] = $rel->getReversedRelation()->getAlias();
            $current_object = $rel->getRelatedObject();
        }
        $reverse_aliases = array_reverse($reverse_aliases);
        $output = implode(self::RELATION_SEPARATOR, $reverse_aliases);
        return $output;
    }

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
     * @return RelationPath
     */
    public function getSubpath($start_index = 0, $length = null)
    {
        if ($start_index == 0 && is_null($length)) {
            return $this->copy();
        }
        
        if (! is_null($length) && ! is_numeric($length)) {
            throw new InvalidArgumentException('Non-numeric length for Relation::subpath($start_index, $length) given!');
        }
        
        // Save the originally passed index for error reporting before doing calculations with it
        $passed_index = $start_index;
        
        if ($start_index < 0) {
            $start_index = $this->countRelations() - $start_index;
        }
        
        if ($start_index === $this->countRelations()) {
            // If the start index matches the end of the relation path, just return an empty path starting with the end object.
            // No need for any further processing as the subpath would be empty in any case.
            return new self($this->getEndObject());
        } elseif ($start_index >= 0 && $start_index < $this->countRelations()) {
            // If the start index fits in the range of relation keys, make the subpath start with the main object of the relation at the index
            $subpath = new self($this->getRelation($start_index)->getMainObject());
        } else {
            throw new OutOfRangeException('Subpath starting with illegal index "' . $start_index . '" requested for relation path "' . $this->toString() . '" with ' . $this->countRelations() . ' relations!');
        }
        
        if (is_null($length)) {
            $end_index = $this->countRelations();
        } elseif ($length < 0) {
            $end_index = $this->countRelations() - $length;
        } else {
            $end_index = $this->countRelations() > ($start_index + $length) ? $start_index + $length : $this->countRelations();
        }
        
        for ($i = $start_index; $i < $end_index; $i ++) {
            $subpath->appendRelation($this->getRelation($i));
        }
        
        return $subpath;
    }

    /**
     * Returns the numeric index of the first occurrence of the given relation in the path or FALSE if the relation was not found.
     *
     * @param Relation $relation            
     * @param boolean $exact_match            
     * @return integer|boolean
     */
    public function getIndexOf(Relation $relation, $exact_match = false)
    {
        foreach ($this->getRelations() as $index => $rel) {
            if ($exact_match && $rel->isExactly($relation)) {
                return $index;
            } elseif (! $exact_match && $rel->is($relation)) {
                return $index;
            }
        }
        return false;
    }

    /**
     * DEPRECATED! Use Attribute->getRelationPath() instead
     * Returns the relation path contained in an attribute alias.
     * If the alias does not contain any relations, returns empty strgin.
     * E.g. for the attribute CUSTOMER__CUSTOMER_GROUP__LABEL it woud return CUSTOMER__CUSTOMER_GRUP.
     * For the attribute LABEL it would return '' since there is no relation prefix.
     *
     * @param string $attribute_alias            
     * @return string relation path separated by the relation separater or empty string if no relation used.
     */
    public static function getRelationPathFromAlias($string)
    {
        $output = substr($string, 0, strrpos($string, self::RELATION_SEPARATOR));
        return $output;
    }

    /**
     * Copies the relation path keeping the start object, but copying all relations
     *
     * @return RelationPath
     */
    public function copy()
    {
        $copy = RelationPathFactory::createForObject($this->getStartObject());
        foreach ($this->getRelations() as $rel) {
            $copy->appendRelation($rel->copy());
        }
        return $copy;
    }

    /**
     * Returns TRUE if the path does not contain any relation and FALSE otherwise
     */
    public function isEmpty()
    {
        if (empty($this->relations)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * TODO Make the staic method relation_path_reverse protected or remove it once all calls are replaced
     * by this method!
     *
     * @return \exface\Core\CommonLogic\Model\RelationPath
     */
    public function reverse()
    {
        return RelationPathFactory::createFromString($this->getEndObject(), self::relationPathReverse($this->toString(), $this->getStartObject()));
    }

    public function getIterator()
    {
        return $this->relations;
    }

    public function getRelationSeparator()
    {
        return self::RELATION_SEPARATOR;
    }

    /**
     * Returns an attribute of the end object specified by it's attribute alias, but with a relation path relative to the start object.
     * E.g. calling getAttributeOfEndObject('POSITION_NO') on the relation path ORDER<-POSITION will return ORDER__POSITION__POSITION_NO
     *
     * @param string $attribute_alias            
     * @return Attribute
     */
    public function getAttributeOfEndObject($attribute_alias)
    {
        return $this->getStartObject()->getAttribute(self::relationPathAdd($this->toString(), $attribute_alias));
    }
}