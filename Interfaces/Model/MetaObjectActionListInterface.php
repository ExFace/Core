<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ActionListInterface;

interface MetaObjectActionListInterface extends ActionListInterface
{
    /**
     *
     * @return ModelInterface
     */
    public function getModel();
    
    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject();
    
    /**
     *
     * @param MetaObjectInterface $meta_object
     * @return \exface\Core\Interfaces\Model\MetaObjectActionListInterface
     */
    public function setMetaObject(MetaObjectInterface $meta_object);
    
    public function getUsedInObjectBasket();
    
    /**
     *
     * @return string[]
     */
    public function getObjectBasketActionAliases();
    
    /**
     *
     * @param array $value
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function setObjectBasketActionAliases(array $value);
}

