<?php
namespace exface\Core\CommonLogic\Traits;

trait MetaModelPrototypeTrait
{
    
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Model\MetaModelPrototypeInterface::getPrototypeClassName()
     */
    public static function getPrototypeClassName()
    {
        return "\\" . __CLASS__;
    }
}