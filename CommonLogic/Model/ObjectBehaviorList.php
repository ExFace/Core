<?php
namespace exface\Core\CommonLogic\Model;

use exface\Core\CommonLogic\EntityList;
use exface\Core\Interfaces\Model\BehaviorInterface;
use exface\Core\Interfaces\Model\BehaviorListInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;

/**
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBehaviorList extends EntityList implements BehaviorListInterface
{

    /**
     * A behavior list will activate every behavior right after it has been added
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::add()
     * @param BehaviorInterface $behavior            
     */
    public function add($behavior, $key = null)
    {
        if (! $behavior->getObject()->isExactly($this->getParent())) {
            $behavior->setObject($this->getParent());
        }
        $result = parent::add($behavior, $key);
        if (! $behavior->isDisabled()) {
            $behavior->register();
        }
        return $result;
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getObject()
    {
        return $this->getParent();
    }

    /**
     *
     * @param string $qualified_alias            
     * @return BehaviorInterface
     */
    public function getByAlias($qualified_alias)
    {
        foreach ($this->getAll() as $behavior) {
            if (strcasecmp($behavior->getAliasWithNamespace(), $qualified_alias) == 0) {
                return $behavior;
            }
        }
        return false;
    }

    /**
     *
     * @param MetaObjectInterface $value            
     * @return ObjectBehaviorList
     */
    public function setObject(MetaObjectInterface $value)
    {
        $this->setParent($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\EntityList::setParent()
     * @param
     *            Object
     */
    public function setParent($object)
    {
        $result = parent::setParent($object);
        foreach ($this->getAll() as $behavior) {
            $behavior->setObject($object);
        }
        return $result;
    }
}