<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeConvertedToUxon;

interface BehaviorListInterface extends iCanBeConvertedToUxon, \IteratorAggregate
{

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
}
?>