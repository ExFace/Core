<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 *
 * 
 * @author Andrej Kabachnik
 *
 */
trait iTakeInputAsDataSubsheetTrait
{
    public function isSubsheetForObject(MetaObjectInterface $objectOfInputData) : bool
    {
        $relPathToParent = $this->getObjectRelationPathToParent();
        return
            ! $objectOfInputData->is($this->getMetaObject())
            || ($relPathToParent !== null && $relPathToParent->isEmpty() === false && $relPathToParent->getEndObject()->isExactly($objectOfInputData));
    }
}