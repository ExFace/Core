<?php

namespace exface\Core\Interfaces\Model;

use exface\Core\CommonLogic\AbstractAction;

/**
 * Implement this interface to signal that instances of this class may affect the state of certain MetaObjects.
 * 
 * @see MetaObjectInterface, AbstractAction
 */
interface IAffectMetaObjectsInterface
{
    /**
     * Retrieve all MetaObjects affected by this instance.
     * 
     * @return MetaObjectInterface[]
     */
    public function getAffectedMetaObjects() : array;
}