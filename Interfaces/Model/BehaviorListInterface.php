<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\EntityListInterface;

interface BehaviorListInterface extends EntityListInterface, iCanBeConvertedToUxon, \IteratorAggregate
{
    /**
     * 
     * @param BehaviorInterface $behavior
     * @param mixed $key
     * @return BehaviorListInterface
     */
    public function add($behavior, $key = null);

    /**
     *
     * @return MetaObjectInterface
     */
    public function getObject();

    /**
     *
     * @param MetaObjectInterface $value            
     * @return BehaviorListInterface
     */
    public function setObject(MetaObjectInterface $value);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getAll()
     * @return BehaviorInterface[]
     */
    public function getAll();
    
    /**
     * 
     * @param string $aliasWithNamespace
     * @return BehaviorListInterface
     */
    public function getByAlias(string $aliasWithNamespace) : BehaviorListInterface;
    
    /**
     * 
     * @param string $uid
     * @return BehaviorInterface|NULL
     */
    public function getByUid(string $uid) : ?BehaviorInterface;
    
    /**
     * 
     * @param string $className
     * @return BehaviorListInterface
     */
    public function getByPrototypeClass(string $className) : BehaviorListInterface;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::remove()
     * @param BehaviorInterface $entity            
     * @return BehaviorListInterface
     */
    public function remove($entity);

    /**
     * Returns the current number of entities in the list.
     *
     * @return integer
     */
    public function count();

    /**
     * Returns TRUE, if the list is empty and FALSE otherwise
     *
     * @return boolean
     */
    public function isEmpty();

    /**
     * Searches for a behavior of the specified type on this object and returns the first match
     * or NULL if no match was found.
     *
     * If `allowMultiple` is set to FALSE, an error will be thrown, if more than one match was found.
     *
     * @param string $class
     * @param bool   $allowMultiple
     * @return BehaviorInterface|null
     * @throws RuntimeException
     */
    public function findBehavior(string $class, bool $allowMultiple = false) : ?BehaviorInterface;

    /**
     * Disables all active behaviors temporarily; enables them again if called with FALSE
     * 
     * This special method allows to turn off and back on active (non-disabled) behaviors. It restores
     * the behaviors completely when called with FALSE.
     * 
     * @param bool $trueOrFalse
     * @return BehaviorListInterface
     */
    public function disableTemporarily(bool $trueOrFalse) : BehaviorListInterface;
}