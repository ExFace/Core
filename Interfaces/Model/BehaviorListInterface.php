<?php

namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\EntityListInterface;

interface BehaviorListInterface extends iCanBeConvertedToUxon, \IteratorAggregate
{

    /**
     *
     * @return Object
     */
    public function getObject();

    /**
     *
     * @param Object $value            
     * @return BehaviorListInterface
     */
    public function setObject(Object $value);

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
     * Uses the given array of UXON objects to populate the entity list.
     * Each UXON object in the array
     * will be instantiated and added to the list.
     * 
     * @param array $uxon            
     * @return void
     */
    public function importUxonArray(array $uxon);
}
?>