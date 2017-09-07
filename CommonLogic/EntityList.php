<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\EntityListInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * The EntityList is a generic container for all kinds of object collections or lists in ExFace.
 * Basically, it is an array with a proper API and some
 * enhancements. It can still be used in foreach() statements just like a normal array. It may be used directly or via derived
 * classes.
 *
 * Using the EntityList instead of arrays is strongly recommended! It ensures, that a common API is used for all sorts of collections like
 * attributes or relations in a meta object, columns in a data sheet, etc. Using the EntityList simplifies the entity classes by taking
 * over all sorts of add_xxx and get_by_xxx methods. The EntityList can be quickly extended by entity-specific getters and setters like
 * get_by_data_type() for data sheet columns, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class EntityList extends AbstractExfaceClass implements EntityListInterface
{

    private $content_array = array();

    private $parent_object = null;

    private $entity_name_resolver = null;

    private $endity_factory_name = null;

    /**
     * An EntityList is alway attached to some parent object, so a reference to that object is required in the constructor
     *
     * @param exface $exface            
     * @param mixed $parent_object            
     */
    public function __construct(Workbench $exface, $parent_object)
    {
        parent::__construct($exface);
        $this->setParent($parent_object);
    }

    public function exportUxonObject()
    {
        $result = array();
        foreach ($this->getAll() as $object) {
            if ($object instanceof iCanBeConvertedToUxon) {
                $result[] = $object->exportUxonObject();
            }
        }
        return $result;
    }

    /**
     * Returns all entities in the list as an array.
     * It is an assosiative array, if key were explicitly speicified when filling the list.
     *
     * @return array
     */
    public function getAll()
    {
        return $this->content_array;
    }

    /**
     *
     * {@inheritdoc} In order to instantiate a list with all it's entities, we need to know, what type of entities they are (UXON will not always have
     *               properties indicating this as they may be ommitted in most cases!). This is why a factory class name should be passed here. The
     *               EntityList will call the factories create_from_uxon() method for each of the direct children of the given UXON object. The factory can
     *               either be specified by a fully qualified class name (with namespace) or just the factory name (like "DataSheetFactory"), which will
     *               then be assumed to be one of the core factories.
     *              
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     *
     * @param UxonObject $uxon            
     * @param string $factory_class_name            
     */
    public function importUxonObject(UxonObject $uxon, $factory_class_name = null)
    {
        if (is_null($factory_class_name)) {
            $factory_class_name = $this->getEntityFactoryName();
        }
        if (is_null($factory_class_name)) {
            throw new UnexpectedValueException('Cannot instantiate EntityList from UXON: factory class for contained objects not speicified!');
        }
        if (! class_exists($factory_class_name)) {
            $factory_class_name = '\\exface\\Core\\Factories\\' . $factory_class_name;
        }
        $exface = $this->getWorkbench();
        foreach ($uxon as $u) {
            $this->add($factory_class_name::from_uxon($exface, $u));
        }
    }

    /**
     * The generic entity list can automatically instantiate any list of UXON objects if a factory class is provided
     * or had been registered for this entity list via set_entity_factory_name().
     * If so, factory::createFromUxon($exface, $uxon)
     * will be called for each element of the array. The resultin entities will be added to the list. This, of course,
     * only works if the entities have a factory, that supports this kind of instantiation.
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::importUxonArray()
     */
    public function importUxonArray(array $uxon, $factory_class_name = null)
    {
        if (is_null($factory_class_name)) {
            $factory_class_name = $this->getEntityFactoryName();
        }
        if (is_null($factory_class_name)) {
            throw new UnexpectedValueException('Cannot instantiate EntityList from UXON: factory class for contained objects not speicified!');
        }
        if (! class_exists($factory_class_name)) {
            $factory_class_name = '\\exface\\Core\\Factories\\' . $factory_class_name;
        }
        $exface = $this->getWorkbench();
        foreach ($uxon as $u) {
            $this->add($factory_class_name::createFromUxon($exface, $u));
        }
    }

    /**
     * Adds an entity to the list under the given key.
     * If no key is given, the entity is appended to the end of the list.
     * CAUTION: it is not advisable to mix entries with keys and without them in one list!
     *
     * @param mixed $entity            
     * @param mixed $key            
     * @return EntityList
     */
    public function add($entity, $key = null)
    {
        if (is_null($key)) {
            $this->content_array[] = $entity;
        } else {
            $this->content_array[$key] = $entity;
        }
        return $this;
    }

    /**
     * Removes the given entity from the list
     *
     * @param mixed $entity            
     * @return UxonObjectList
     */
    public function remove($entity)
    {
        if ($key = array_search($entity, $this->content_array)) {
            $this->removeByKey($key);
        }
        return $this;
    }

    /**
     * Removes the entity matching the given key
     *
     * @param mixed $key            
     */
    public function removeByKey($key)
    {
        unset($this->content_array[$key]);
        return $this;
    }

    /**
     * Removes all entities from the list, thus emptying the list.
     *
     * @return EntityList
     */
    public function removeAll()
    {
        foreach ($this->getAll() as $key => $entity) {
            $this->removeByKey($key);
        }
        // Reset the array completely after performing the remove() method for each entry.
        $this->reset();
        return $this;
    }

    /**
     * Returns the entity, that was stored under the given key.
     * Returns NULL if the key is not present in the list.
     *
     * @param mixed $key            
     * @return mixed
     */
    public function get($key)
    {
        return $this->content_array[$key];
    }

    /**
     * Returns the first entity in the list or NULL if the list is empty.
     *
     * @return mixed
     */
    public function getFirst()
    {
        return array_values($this->getAll())[0];
    }

    /**
     * Returns the last entity in the list or NULL if the list is empty.
     *
     * @return mixed
     */
    public function getLast()
    {
        $values = array_values($this->getAll());
        return $values[(count($values) - 1)];
    }

    /**
     * Returns the n-th entity in the list (starting from 1 for the first entity).
     * Returns NULL if the list is smaller than $number.
     *
     * @param integer $number            
     */
    public function getNth($number)
    {
        $i = 1;
        foreach ($this->getAll() as $entity) {
            if ($i == $number) {
                return $entity;
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->content_array);
    }

    /**
     * Returns the lists parent object
     */
    public function getParent()
    {
        return $this->parent_sheet;
    }

    /**
     * Sets the lists parent object
     *
     * @param mixed $parent_object            
     */
    public function setParent($parent_object)
    {
        $this->parent_sheet = $parent_object;
        return $this;
    }

    /**
     * Returns the current number of entities in the list.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->getAll());
    }

    /**
     * Returns TRUE, if the list is empty and FALSE otherwise
     *
     * @return boolean
     */
    public function isEmpty()
    {
        if ($this->count() > 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns the name resolver for the entities in the list
     *
     * @return NameResolver
     */
    public function getEntityNameResolver()
    {
        return $this->entity_name_resolver;
    }

    /**
     * Setting the name resolver allows the list to know exactly what it's contents can be an where to find the corresponding classes
     * and factories.
     * The name resolver takes care of resolving all the name conventions in ExFace.
     *
     * @param NameResolver $value            
     */
    public function setEntityNameResolver(NameResolverInterface $value)
    {
        $this->entity_name_resolver = $value;
        return $this;
    }

    /**
     * Sorts the list by key values
     *
     * @return EntityList
     */
    public function sortByKey()
    {
        ksort($this->content_array);
        return $this;
    }

    public function getEntityFactoryName()
    {
        if (is_null($this->endity_factory_name)) {
            if ($this->getEntityNameResolver() && $factory = $this->getEntityNameResolver()->getFactoryClassName()) {
                return $factory;
            }
        }
        return $this->entity_factory_name;
    }

    public function setEntityFactoryName($value)
    {
        $this->entity_factory_name = $value;
        return $this;
    }

    /**
     * Resets the internal array without doing anything else! Thus, the parent object will not be affected! If
     * you want to remove all entities, use remove_all() instead.
     * reset() is only ment for internal purposes!
     */
    protected final function reset()
    {
        $this->content_array = array();
        return $this;
    }

    /**
     * Copies the entire list including all entities by calling the copy() method on each entity
     *
     * @throws InvalidArgumentException
     * @return EntityList
     */
    public function copy()
    {
        $copy = clone $this;
        $copy->reset();
        foreach ($this->content_array as $key => $entity) {
            if (! ($entity instanceof iCanBeCopied)) {
                throw new InvalidArgumentException('Cannot use the generic copy() method for a list with entities, that do not implement the iCanBeCopied interface!');
            }
            $copy->content_array[$key] = $entity->copy();
        }
        return $copy;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::merge()
     */
    public function merge(EntityListInterface $other_list)
    {
        if (get_class($this) !== get_class($other_list)) {
            throw new InvalidArgumentException('Cannot merge entity lists of different types ("' . get_class($this) . '" and "' . get_class($other_list) . '")!');
            return $this;
        }
        $exface = $this->getWorkbench();
        $parent_object = $this->getParent();
        $result = new self($exface, $parent_object);
        $entities = array_merge($this->getAll(), $other_list->getAll());
        foreach ($entities as $key => $entity) {
            $result->add($entity, $key);
        }
        return $result;
    }
}
?>