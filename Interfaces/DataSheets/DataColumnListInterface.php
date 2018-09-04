<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\EntityListInterface;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

/**
 *
 * @method DataColumnInterface[] getAll()
 * @method DataColumnInterface get(string $key)
 * @method DataColumnInterface getFirst()
 * @method DataColumnInterface getLast()
 *        
 * @author Andrej Kabachnik
 *        
 */
interface DataColumnListInterface extends EntityListInterface
{

    /**
     * Returns all elements as an array
     *
     * @return DataColumnInterface[]
     */
    public function getAll();

    /**
     * Returns the first element of the list
     *
     * @return DataColumnInterface
     */
    public function getFirst();

    /**
     * Returns the last element of the list
     *
     * @return DataColumnInterface
     */
    public function getLast();

    /**
     * Adds an entity to the list under the given key.
     * If no key is given, the entity is appended to the end of the list.
     * CAUTION: it is not advisable to mix entries with keys and without them in one list!
     *
     * @param DataColumnInterface $entity            
     * @param mixed $key            
     * @return DataColumnListInterface
     */
    public function add($entity, $key = null);

    /**
     * Adds a new column from an instatiated expression object or a string expression and returns it.
     *
     * @param \exface\Core\Interfaces\Model\ExpressionInterface|string $expression            
     * @param string $name            
     * @param string $hidden            
     * @return DataColumnInterface
     */
    public function addFromExpression($expression_or_string, $name = null, $hidden = false);

    /**
     * Adds a new column with the given attribute and returns it
     *
     * @param MetaAttributeInterface $attribute            
     * @return DataColumnInterface
     */
    public function addFromAttribute(MetaAttributeInterface $attribute);
    
    /**
     * 
     * @return DataColumnInterface
     */
    public function addFromUidAttribute() : DataColumnInterface;
    
    /**
     * 
     * @return DataColumnInterface
     */
    public function addFromLabelAttribute() : DataColumnInterface;

    /**
     * Removes the given entity from the list
     *
     * @param DataColumnInterface $entity            
     * @return DataColumnListInterface
     */
    public function remove($entity);

    /**
     * Returns the entity, that was stored under the given key.
     * Returns NULL if the key is not present in the list.
     *
     * @param mixed $key            
     * @return DataColumnListInterface
     */
    public function get($key);

    /**
     * Returns the n-th entity in the list (starting from 1 for the first entity).
     * Returns NULL if the list is smaller than $number.
     *
     * @param integer $number            
     */
    public function getNth($number);

    /**
     * Returns the lists parent object
     *
     * @return DataSheetInterface
     */
    public function getParent();

    /**
     * Sets the lists parent object
     *
     * @param DataSheetInterface $parent_object            
     */
    public function setParent($parent_object);

    /**
     * Returns the first data column matching the given expression or FALSE if no matching column is found.
     * The expression can be passed as a string or an instantiated expression object.
     *
     * @param
     *            expression | string $expression_or_string
     * @return DataColumnInterface|boolean
     */
    public function getByExpression($expression_or_string);

    /**
     * Returns the first column, that shows the specified attribute explicitly (not within a formula).
     * Returns FALSE if no column is found.
     *
     * @param MetaAttributeInterface $attribute            
     * @return DataColumnInterface|boolean
     */
    public function getByAttribute(MetaAttributeInterface $attribute);
}
?>