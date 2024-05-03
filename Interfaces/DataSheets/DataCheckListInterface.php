<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\EntityListInterface;

/**
 *
 * @author Andrej Kabachnik
 * 
 * @method DataCheckInterface get()
 * @method DataCheckInterface getFirst()
 * @method DataCheckInterface[] getAll()
 * @method DataCheckInterface|DataCheckInterface[] getIterator()
 *        
 */
interface DataCheckListInterface extends EntityListInterface
{
    /**
     * 
     * @param MetaObjectInterface $object
     * @return DataCheckListInterface
     */
    public function getForObject(MetaObjectInterface $object) : DataCheckListInterface;
}