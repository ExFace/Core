<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\Model\MetaObjectActionListInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class MetaObjectActionList extends ActionList implements MetaObjectActionListInterface
{

    private $object_basket_action_aliases = array();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\Model\ActionList::add()
     */
    public function add($action, $key = null)
    {
        $action->setMetaObject($this->getMetaObject());
        return parent::add($action, $key);
    }

    /**
     *
     * @return model
     */
    public function getModel()
    {
        return $this->getMetaObject()->getModel();
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject()
    {
        return $this->getParent();
    }

    /**
     *
     * @param MetaObjectInterface $meta_object            
     * @return \exface\Core\Interfaces\Model\MetaObjectActionListInterface
     */
    public function setMetaObject(MetaObjectInterface $meta_object)
    {
        return $this->setParent($meta_object);
    }

    public function getUsedInObjectBasket()
    {
        $list = clone $this;
        $list->removeAll();
        foreach ($this->getAll() as $action) {
            if (in_array($action->getAliasWithNamespace(), $this->getObjectBasketActionAliases())) {
                $list->add($action);
            }
        }
        return $list;
    }

    /**
     *
     * @return string[]
     */
    public function getObjectBasketActionAliases()
    {
        return $this->object_basket_action_aliases;
    }

    /**
     *
     * @param array $value            
     * @return \exface\Core\Interfaces\Model\MetaObjectInterface
     */
    public function setObjectBasketActionAliases(array $value)
    {
        $this->object_basket_action_aliases = $value;
        return $this;
    }
}