<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Factories\RelationPathFactory;
use exface\Core\Exceptions\Model\MetaRelationNotFoundError;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\OutOfRangeException;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaRelationInterface;
use exface\Core\Interfaces\Model\MetaRelationPathInterface;
use Traversable;

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
class RelationPath implements MetaRelationPathInterface
{

    const RELATION_SEPARATOR = '__';

    /* Properties to be dublicated on copy() */
    private $relations = array();

    /* Properties NOT to be dublicated on copy() */
    private $start_object = null;

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::__constuct()
     */
    public function __construct(MetaObjectInterface $start_object)
    {
        $this->start_object = $start_object;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getRelations()
     */
    public function getRelations()
    {
        return $this->relations;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::appendRelation()
     */
    public function appendRelation(MetaRelationInterface $relation)
    {
        $this->relations[] = $relation;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::prependRelation()
     */
    public function prependRelation(MetaRelationInterface $relation)
    {
        array_unshift($this->relations, $relation);
        $this->start_object = $relation->getLeftObject();
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::appendRelationsFromStringPath()
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
            // Rethrow the error if it's just an invalid alias
            if (! $this->getEndObject()->hasAttribute($first_rel)) {
                throw $e;
            }
            // Do nothing if it is not a relation, but a regular attribute. It's relation path is already built.
        }
        
        if ($first_rel != $relation_path_string) {
            return $this->appendRelationsFromStringPath(substr($relation_path_string, (strlen($first_rel) + strlen(self::RELATION_SEPARATOR))));
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getFirstRelationFromStringPath()
     */
    protected static function getFirstRelationFromStringPath($string_relation_path)
    {
        return explode(self::RELATION_SEPARATOR, $string_relation_path, 2)[0];
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::toString()
     */
    public function toString()
    {
        $path = '';
        foreach ($this->getRelations() as $rel) {
            $path = $path . self::RELATION_SEPARATOR . $rel->getAliasWithModifier();
        }
        $path = trim($path, self::RELATION_SEPARATOR);
        return $path;
    }
    
    /**
     * 
     * @return string
     */
    public function __toString() : string
    {
        return $this->toString();
    }

    public function getWorkbench()
    {
        return $this->getStartObject()->getModel()->getWorkbench();
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getStartObject()
     */
    public function getStartObject()
    {
        return $this->start_object;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getEndObject()
     */
    public function getEndObject()
    {
        if (! $this->isEmpty()) {
            return $this->getRelationLast()->getRightObject();
        } else {
            return $this->getStartObject();
        }
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getRelation()
     */
    public function getRelation($index)
    {
        return $this->getRelations()[$index];
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getRelationFirst()
     */
    public function getRelationFirst()
    {
        return $this->getRelation(0);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getRelationLast()
     */
    public function getRelationLast()
    {
        return $this->getRelation($this->countRelations() - 1);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::countRelations()
     */
    public function countRelations()
    {
        return count($this->getRelations());
    }

    /**
     * @deprecated! Use RelationPathFactory::createFromString() instead!
     * 
     * Checks if the given alias includes a relation path and returns an array with relation aliases
     *
     * @param string $col            
     * @param int $depth            
     *
     * @return string[]|NULL
     */
    public static function relationPathParse($alias, $depth = 0) : ?array
    {
        $depth = intval($depth);
        
        $sep = self::RELATION_SEPARATOR;
        if (mb_strpos($alias, $sep) === false) {
            return null;
        } else {
            if ($depth) {
                return explode($sep, $alias, $depth + 1);
            } else {
                return explode($sep, $alias);
            }
        }
    }

    /**
     * Append one relation path string to another one
     * 
     * To join two relation path instances, use `combine()` instead. To append a relation to a 
     * relation path instance, use `appendRelation()`.
     * 
     * @param string|null $path1
     * @param string|null $path2
     * @return string
     */
    public static function join(?string $path1, ?string $path2) : string
    {
        $output = '';
        if ($path1 !== '' && $path1 !== null) {
            $output = $path1;
        }
        if ($path2 !== '' && $path2 !== null) {
            $output = $output ? $output . self::RELATION_SEPARATOR . $path2 : $path2;
        }
        
        return $output;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::combine()
     */
    public function combine(MetaRelationPathInterface $path_to_append)
    {
        $result = $this->copy();
        foreach ($path_to_append as $relation) {
            $result->appendRelation($relation);
        }
        return $result;
    }

    /**
     * @deprecated! Use reverse() instead!
     * 
     * Reverses a given relation path relative to a given object: POSITION->PRODUCT relative to an ORDER will become POSITION->ORDER and thus can now be
     * used in the context of a PRODUCT.
     *
     * @param string $relation_path            
     * @param MetaObjectInterface $meta_object            
     * @return string
     */
    public static function relationPathReverse($relation_path, MetaObjectInterface $meta_object)
    {
        if ($relation_path === '') {
            return '';
        }
        
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
            
            $reverse_aliases[] = $rel->getReversedRelation()->getAliasWithModifier();
            $current_object = $rel->getRightObject();
        }
        $reverse_aliases = array_reverse($reverse_aliases);
        $output = implode(self::RELATION_SEPARATOR, $reverse_aliases);
        return $output;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getSubpath()
     */
    public function getSubpath($start_index = 0, $length = null)
    {
        if ($start_index == 0 && is_null($length)) {
            return $this->copy();
        }
        
        if (! is_null($length) && ! is_numeric($length)) {
            throw new InvalidArgumentException('Non-numeric length for MetaRelationInterface::subpath($start_index, $length) given!');
        }
        
        if ($start_index < 0) {
            $start_index = $this->countRelations() + $start_index;
        }
        
        if ($start_index === $this->countRelations()) {
            // If the start index matches the end of the relation path, just return an empty path starting with the end object.
            // No need for any further processing as the subpath would be empty in any case.
            return new self($this->getEndObject());
        } elseif ($start_index >= 0 && $start_index < $this->countRelations()) {
            // If the start index fits in the range of relation keys, make the subpath start with the main object of the relation at the index
            $subpath = new self($this->getRelation($start_index)->getLeftObject());
        } else {
            throw new OutOfRangeException('Subpath starting with illegal index "' . $start_index . '" requested for relation path "' . $this->toString() . '" with ' . $this->countRelations() . ' relations!');
        }
        
        if (is_null($length)) {
            $end_index = $this->countRelations();
        } elseif ($length < 0) {
            $end_index = $this->countRelations() + $length;
        } else {
            $end_index = $this->countRelations() > ($start_index + $length) ? $start_index + $length : $this->countRelations();
        }
        
        for ($i = $start_index; $i < $end_index; $i ++) {
            $subpath->appendRelation($this->getRelation($i));
        }
        
        return $subpath;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getIndexOf()
     */
    public function getIndexOf(MetaRelationInterface $relation, $exact_match = false)
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
     * @deprecated! Use Attribute->getRelationPath() instead
     * 
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

    public static function slice(string $path, int $pos = 1) : ?array
    {      
        if (mb_strpos($path, self::RELATION_SEPARATOR) === false) {
            return null;
        }  
        $rels = explode(self::RELATION_SEPARATOR, $path);
        return [
            implode(self::RELATION_SEPARATOR, array_slice($rels, 0, $pos)),
            implode(self::RELATION_SEPARATOR, array_slice($rels, $pos))
        ];            
    }

    /**
     * Copies the relation path keeping the start object, but copying all relations
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanBeCopied::copy()
     */
    public function copy() : self
    {
        $copy = RelationPathFactory::createForObject($this->getStartObject());
        foreach ($this->getRelations() as $rel) {
            $copy->appendRelation($rel->copy());
        }
        return $copy;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::isEmpty()
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
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::reverse()
     */
    public function reverse()
    {
        return RelationPathFactory::createFromString($this->getEndObject(), self::relationPathReverse($this->toString(), $this->getStartObject()));
    }

    /**
     * 
     * {@inheritDoc}
     * @see \IteratorAggregate::getIterator()
     */
    public function getIterator() : Traversable
    {
        return new \ArrayIterator($this->relations);
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getRelationSeparator()
     */
    public static function getRelationSeparator()
    {
        return self::RELATION_SEPARATOR;
    }

    /**
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::getAttributeOfEndObject()
     */
    public function getAttributeOfEndObject($attribute_alias)
    {
        return $this->getStartObject()->getAttribute(self::join($this->toString(), $attribute_alias));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaRelationPathInterface::containsReverseRelations()
     */
    public function containsReverseRelations() : bool
    {
        foreach ($this->getRelations() as $rel) {
            if ($rel->isReverseRelation()) {
                return true;
            }
        }
        return false;
    }
}