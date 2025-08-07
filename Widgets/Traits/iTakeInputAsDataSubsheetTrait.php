<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Widgets\iTakeInputAsDataSubsheet;

/**
 * Default implemention of interface iTakeInputAsDataSubsheet
 * 
 * @author Andrej Kabachnik
 * @see iTakeInputAsDataSubsheet
 */
trait iTakeInputAsDataSubsheetTrait
{
    public function isSubsheetForObject(MetaObjectInterface $objectOfInputData) : bool
    {
        if ($this->isDisplayOnly() === true) {
            return false;
        }
        if ($this->hasParent() === false) {
            return false;
        }
        $relPathToParent = $this->getObjectRelationPathToParent();
        return
            ! $objectOfInputData->is($this->getMetaObject())
            || ($relPathToParent !== null && $relPathToParent->isEmpty() === false && $relPathToParent->getEndObject()->isExactly($objectOfInputData));
    }
}