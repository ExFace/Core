<?php
namespace exface\Core\CommonLogic\Model\Behaviors;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSheets\DataCheckInterface;
use exface\Core\Interfaces\DataSheets\DataCheckListInterface;
use exface\Core\Interfaces\Model\BehaviorInterface;

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
class BehaviorDataCheckList extends EntityList implements DataCheckListInterface
{
    /**
     * 
     * @return BehaviorInterface
     */
    public function getBehavior() : BehaviorInterface
    {
        return $this->getParent();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Actions\ActionDataCheckListInterface::getForObject()
     */
    public function getForObject(MetaObjectInterface $object) : DataCheckListInterface
    {
        return $this->filter(function(DataCheckInterface $check) use ($object) {
            return $check->isApplicableToObject($object);
        });
    }
}