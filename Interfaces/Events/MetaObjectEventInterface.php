<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\Model\MetaObjectInterface;

interface MetaObjectEventInterface extends EventInterface
{
    /**
     * Returns the object, for which the event was triggered.
     * 
     * @return MetaObjectInterface
     */
    public function getObject() : MetaObjectInterface;
}