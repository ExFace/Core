<?php
namespace exface\Core\Interfaces\Actions;

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
interface ActionDataCheckListInterface extends EntityListInterface
{
    public function getAction() : ActionInterface;
    
    public function setDisabled(bool $trueOrFalse): ActionDataCheckListInterface;
    
    public function isDisabled() : bool;
    
    public function getForObject(MetaObjectInterface $object) : ActionDataCheckListInterface;
}